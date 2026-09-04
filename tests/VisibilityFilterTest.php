<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Query;
use ipl\Sql\Test\SqlAssertions;
use ipl\Stdlib\Filter;
use ipl\Tests\Orm\Lib\Model\Book;
use ipl\Tests\Orm\Lib\Model\Department;
use ipl\Tests\Orm\Lib\Model\Node;
use ipl\Tests\Orm\Lib\Model\RestrictedGroup;
use ipl\Tests\Orm\Lib\Model\RestrictedUser;
use PHPUnit\Framework\TestCase;

class VisibilityFilterTest extends TestCase
{
    use SqlAssertions;

    public function setUp(): void
    {
        $this->setUpSqlAssertions();
    }

    public function testBaseModelVisibilityFilterIsAppliedToWhereClause()
    {
        $query = (new Query())
            ->setModel(new RestrictedGroup());

        $this->assertSql(
            <<<'SQL'
            SELECT restricted_group.id, restricted_group.name, restricted_group.deleted
            FROM restricted_group
            WHERE restricted_group.deleted = ?
            SQL,
            $query->assembleSelect(),
            ['n']
        );
    }

    public function testModelWithoutVisibilityFilterAddsNoWhereClause()
    {
        $query = (new Query())
            ->setModel(new User())
            ->columns('username');

        $this->assertSql(
            'SELECT user.username FROM user',
            $query->assembleSelect()
        );
    }

    public function testJoinedTargetModelVisibilityFilterIsAppliedToJoinCondition()
    {
        $query = (new Query())
            ->setModel(new RestrictedUser())
            ->columns('username')
            ->utilize('restricted_group');

        $this->assertSql(
            <<<'SQL'
            SELECT restricted_user.username
            FROM restricted_user
            INNER JOIN restricted_group restricted_user_restricted_group
                ON (restricted_user_restricted_group.restricted_user_id = restricted_user.id)
                AND (restricted_user_restricted_group.deleted = ?)
            SQL,
            $query->assembleSelect(),
            ['n']
        );
    }

    public function testSelfReferencingRelationAppliesTheTargetsVisibilityFilterToTheTarget()
    {
        $query = Node::on(new TestConnection())
            ->columns('name')
            ->utilize('parent');

        $this->assertSql(
            <<<'SQL'
        SELECT node.name
        FROM node
        LEFT JOIN node node_parent
            ON (node_parent.id = node.parent_id)
            AND (node_parent.deleted = ?)
        WHERE node.deleted = ?
        SQL,
            $query->assembleSelect(),
            ['n', 'n']
        );
    }

    public function testSelfReferencingRelationFilterIsAppliedToTheTarget()
    {
        $query = Node::on(new TestConnection())
            ->columns('name')
            ->utilize('child');

        $this->assertSql(
            <<<'SQL'
        SELECT node.name
        FROM node
        INNER JOIN node node_child
            ON (node_child.parent_id = node.id)
            AND ((node_child.name = ?) AND (node_child.deleted = ?))
        WHERE node.deleted = ?
        SQL,
            $query->assembleSelect(),
            ['foo', 'n', 'n']
        );
    }

    public function testSelfReferencingRelationFilterCanBeFilteredByItsName()
    {
        $query = Node::on(new TestConnection())
            ->columns('name')
            ->filter(Filter::equal('child.name', 'John Doe'));

        $this->assertSql(
            <<<'SQL'
        SELECT node.name
        FROM node
        WHERE (node.deleted = ?) AND (node.id IN ((SELECT sub_node_node.id AS sub_node_node_id
        FROM node sub_node
        INNER JOIN node sub_node_node ON (sub_node_node.id = sub_node.parent_id)
        AND ((sub_node.name = ?) AND (sub_node_node.deleted = ?))
        WHERE (sub_node.deleted = ?) AND (sub_node.name = ?))))
        SQL,
            $query->assembleSelect(),
            ['n', 'foo', 'n', 'n', 'John Doe']
        );
    }

    public function testRelationFilterIsAppliedToJoinCondition()
    {
        $query = (new Query())
            ->setModel(new RestrictedUser())
            ->columns('username')
            ->utilize('vip_group');

        $this->assertSql(
            <<<'SQL'
            SELECT restricted_user.username
            FROM restricted_user
            INNER JOIN group restricted_user_vip_group
                ON (restricted_user_vip_group.restricted_user_id = restricted_user.id)
                AND (restricted_user_vip_group.name = ?)
            SQL,
            $query->assembleSelect(),
            ['vip']
        );
    }

    public function testRelationFilterMayReferenceTheSourceTable()
    {
        // The "lead" relation's filter references the target (role, default) and the source (department.name)
        $query = (new Query())
            ->setModel(new Department())
            ->columns('name')
            ->utilize('lead');

        $this->assertSql(
            'SELECT department.name FROM department'
            . ' INNER JOIN employee department_lead ON (department_lead.department_id = department.id)'
            . ' AND (((department_lead.role = ?) AND (department.name = ?)) AND (department_lead.deleted = ?))',
            $query->assembleSelect(),
            ['lead', 'Engineering', 'n']
        );
    }

    public function testRelationFilterReferencingSourceAndTargetIsAppliedAsIsInAReversedSubQuery()
    {
        // Filtering on the to-many "lead" relation reverses the join. The relation filter must still apply
        // unchanged: role constrains the target (now the sub query's base) and department.name the source
        // (now joined), because both are addressed by their table alias regardless of the join direction.
        $query = (new Query())
            ->setDb(new TestConnection())
            ->setModel(new Department())
            ->columns('name')
            ->filter(Filter::equal('lead.name', 'x'));

        $this->assertSql(
            'SELECT department.name FROM department WHERE department.id IN ((SELECT'
            . ' sub_employee_department.id AS sub_employee_department_id FROM employee sub_employee'
            . ' INNER JOIN department sub_employee_department'
            . ' ON (sub_employee_department.id = sub_employee.department_id)'
            . ' AND ((sub_employee.role = ?) AND (sub_employee_department.name = ?))'
            . ' WHERE (sub_employee.deleted = ?) AND (sub_employee.name = ?)))',
            $query->assembleSelect(),
            ['lead', 'Engineering', 'n', 'x']
        );
    }

    public function testBelongsToManyThroughAndRelationFiltersAreAppliedToJoinConditions()
    {
        $query = (new Query())
            ->setModel(new RestrictedUser())
            ->columns('username')
            ->utilize('car');

        $this->assertSql(
            <<<'SQL'
            SELECT restricted_user.username
            FROM restricted_user
            INNER JOIN car_user restricted_user_car_user
                ON (restricted_user_car_user.restricted_user_id = restricted_user.id)
                AND (restricted_user_car_user.user_id = ?)
            INNER JOIN car restricted_user_car
                ON (restricted_user_car.id = restricted_user_car_user.car_id)
                AND (restricted_user_car.manufacturer = ?)
            SQL,
            $query->assembleSelect(),
            [5, 'Icinga']
        );
    }

    public function testBelongsToManyThroughAndRelationFiltersAreAppliedToReversedJoinConditionsInASubQuery(): void
    {
        $query = (new Query())
            ->setDb(new TestConnection())
            ->setModel(new RestrictedUser())
            ->filter(Filter::equal('car.model_name', 'volkswagen'));

        $this->assertSql(
            <<<'SQL'
            SELECT restricted_user.id, restricted_user.username
            FROM restricted_user
            WHERE restricted_user.id IN
                 ((SELECT sub_car_restricted_user.id AS sub_car_restricted_user_id
                 FROM car sub_car
                     INNER JOIN car_user sub_car_car_user
                         ON (sub_car_car_user.car_id = sub_car.id)
                                AND (sub_car_car_user.user_id = ?)
                     INNER JOIN restricted_user sub_car_restricted_user
                         ON (sub_car_restricted_user.id = sub_car_car_user.restricted_user_id)
                                AND (sub_car.manufacturer = ?)
                 WHERE sub_car.model_name = ?))
            SQL,
            $query->assembleSelect(),
            [5, 'Icinga', 'volkswagen']
        );
    }

    public function testBelongsToManyWithMandatoryKeysJoinsCorrectly()
    {
        // Sanity anchor for the forward direction of the reversal regression below
        $query = (new Query())
            ->setModel(new Book())
            ->columns('title')
            ->utilize('author');

        $this->assertSql(
            'SELECT book.title FROM book'
            . ' INNER JOIN authorship book_authorship ON book_authorship.authored_book = book.book_no'
            . ' INNER JOIN author book_author ON book_author.author_ref = book_authorship.authoring',
            $query->assembleSelect()
        );
    }

    public function testBelongsToManyWithMandatoryKeysReversesThemCorrectlyInASubQuery()
    {
        // Book->author uses a plain junction and non-conventional keys that must be declared explicitly.
        // When reversed for the sub-query there is no junction model or default to re-derive them from, so
        // the source-side and target-side key pairs must be exchanged as a whole. Regression for the bug
        // where BelongsToMany::reverse() only flipped candidate<->foreign within each side.
        $query = (new Query())
            ->setDb(new TestConnection())
            ->setModel(new Book())
            ->columns('title')
            ->filter(Filter::equal('author.name', 'x'));

        $this->assertSql(
            'SELECT book.title FROM book WHERE book.book_no IN ((SELECT'
            . ' sub_author_book.book_no AS sub_author_book_book_no FROM author sub_author'
            . ' INNER JOIN authorship sub_author_authorship'
            . ' ON sub_author_authorship.authoring = sub_author.author_ref'
            . ' INNER JOIN book sub_author_book ON sub_author_book.book_no = sub_author_authorship.authored_book'
            . ' WHERE sub_author.name = ?))',
            $query->assembleSelect(),
            ['x']
        );
    }

    public function testBelongsToManyThroughFilterIsNotValidatedForPlainJunctions()
    {
        // A plain junction (no through-model) has no selectable columns of its own, so its
        // through filter columns must not be validated but still be qualified and applied.
        $query = (new Query())
            ->setModel(new RestrictedUser())
            ->columns('username')
            ->utilize('shared_group');

        $this->assertSql(
            <<<'SQL'
            SELECT restricted_user.username
            FROM restricted_user
            INNER JOIN user_group restricted_user_sg
                ON (restricted_user_sg.restricted_user_id = restricted_user.id)
                AND (restricted_user_sg.active = ?)
            INNER JOIN group restricted_user_shared_group
                ON restricted_user_shared_group.id = restricted_user_sg.group_id
            SQL,
            $query->assembleSelect(),
            ['y']
        );
    }

    public function testModelVisibilityAndRelationFiltersAreAppliedInAFilterSubQuery()
    {
        // Filtering on a to-many relation is turned into a reversed sub query by the FilterProcessor.
        // The sub query's base (employee) must carry both its own visibility filter (deleted = n) and
        // the relation filter declared on the forward relation (active = y).
        $query = (new Query())
            ->setDb(new TestConnection())
            ->setModel(new Department())
            ->columns('name')
            ->filter(Filter::equal('employee.name', 'x'));

        $this->assertSql(
            'SELECT department.name FROM department WHERE department.id IN ((SELECT'
            . ' sub_employee_department.id AS sub_employee_department_id FROM employee sub_employee'
            . ' INNER JOIN department sub_employee_department'
            . ' ON (sub_employee_department.id = sub_employee.department_id) AND (sub_employee.active = ?)'
            . ' WHERE (sub_employee.deleted = ?) AND (sub_employee.name = ?)))',
            $query->assembleSelect(),
            ['y', 'n', 'x']
        );
    }

    public function testFiltersArePropagatedThroughTheReversedJoinsOfASubQuery()
    {
        // Two-hop path: the sub query's base is the final target (ticket) and the intermediate model
        // (employee) is joined. Each must receive the filters that constrain it: ticket gets the relation
        // filter of employee->ticket (open = y), employee gets both its visibility filter (deleted = n)
        // and the relation filter of department->employee (active = y).
        $query = (new Query())
            ->setDb(new TestConnection())
            ->setModel(new Department())
            ->columns('name')
            ->filter(Filter::equal('employee.ticket.subject', 'x'));

        $this->assertSql(
            'SELECT department.name FROM department WHERE department.id IN ((SELECT'
            . ' sub_ticket_employee_department.id AS sub_ticket_employee_department_id FROM ticket sub_ticket'
            . ' INNER JOIN employee sub_ticket_employee ON (sub_ticket_employee.id = sub_ticket.employee_id)'
            . ' AND ((sub_ticket.open = ?) AND (sub_ticket_employee.deleted = ?))'
            . ' INNER JOIN department sub_ticket_employee_department'
            . ' ON (sub_ticket_employee_department.id = sub_ticket_employee.department_id)'
            . ' AND (sub_ticket_employee.active = ?) WHERE sub_ticket.subject = ?))',
            $query->assembleSelect(),
            ['y', 'n', 'y', 'x']
        );
    }

    public function testDeriveAppliesTheModelVisibilityFilterAndTheRelationFilter()
    {
        // derive() loads a relation for a concrete source model via a reversed sub query. Since the
        // relation's target becomes the sub query's base, its visibility filter (deleted = n) ends up in the
        // WHERE, while the relation filter (active = y) is carried by the inverse join. Both are applied by
        // createSubQuery alone, each exactly once and qualified with the sub query's alias (sub_employee).
        $query = (new Query())
            ->setDb(new TestConnection())
            ->setModel(new Department());

        $derived = $query->derive('employee', new Department(['id' => 1]));

        $this->assertSql(
            'SELECT employee.id, employee.name, employee.active, employee.deleted,'
            . ' employee.role, employee.department_id, employee.office_id'
            . ' FROM employee'
            . ' INNER JOIN department employee_self'
            . ' ON (employee_self.id = employee.department_id) AND (employee.active = ?)'
            . ' WHERE (employee.deleted = ?) AND (employee_self.id = ?)',
            $derived->assembleSelect(),
            ['y', 'n', 1]
        );
    }

    public function testDeriveAppliesARelationFilterThatReferencesTheSourceTable()
    {
        // The "lead" relation filter references both the target (role) and the source (department.name).
        // Both must be qualified with the respective sub query aliases without deriving the filter twice.
        $query = (new Query())
            ->setDb(new TestConnection())
            ->setModel(new Department());

        $derived = $query->derive('lead', new Department(['id' => 1]));

        $this->assertSql(
            'SELECT employee.id, employee.name, employee.active, employee.deleted,'
            . ' employee.role, employee.department_id, employee.office_id'
            . ' FROM employee'
            . ' INNER JOIN department employee_self'
            . ' ON (employee_self.id = employee.department_id)'
            . ' AND ((employee.role = ?) AND (employee_self.name = ?))'
            . ' WHERE (employee.deleted = ?) AND (employee_self.id = ?)',
            $derived->assembleSelect(),
            ['lead', 'Engineering', 'n', 1]
        );
    }

    public function testSubQueryReversalEmitsADeprecationWhenARelationUsesTheDefaultReverseName()
    {
        // Filtering through "lead" (whose name differs from its target's table alias "employee") into the
        // deeper to-many "ticket" reverses two hops. Reversing the ticket hop falls back to the source's
        // table alias ("employee"), which differs from the path segment ("lead"), so a deprecation nudges
        // towards setReverseName(). The produced SQL still uses the path segment for backwards compatibility.
        $query = (new Query())
            ->setDb(new TestConnection())
            ->setModel(new Department())
            ->columns('name')
            ->filter(Filter::equal('lead.ticket.subject', 'x'));

        $deprecations = [];
        set_error_handler(function ($_, $message) use (&$deprecations) {
            $deprecations[] = $message;

            return true;
        }, E_USER_DEPRECATED);

        try {
            $select = $query->assembleSelect();
        } finally {
            restore_error_handler();
        }

        $this->assertNotEmpty($deprecations, 'Reversal did not emit a deprecation');
        $this->assertStringContainsString(
            'Relation "department.lead.ticket" still uses the default table alias during reversal',
            $deprecations[0]
        );

        $this->assertSql(
            'SELECT department.name FROM department WHERE department.id IN ((SELECT'
            . ' sub_ticket_lead_department.id AS sub_ticket_lead_department_id FROM ticket sub_ticket'
            . ' INNER JOIN employee sub_ticket_lead ON (sub_ticket_lead.id = sub_ticket.employee_id)'
            . ' AND ((sub_ticket.open = ?) AND (sub_ticket_lead.deleted = ?))'
            . ' INNER JOIN department sub_ticket_lead_department'
            . ' ON (sub_ticket_lead_department.id = sub_ticket_lead.department_id)'
            . ' AND ((sub_ticket_lead.role = ?) AND (sub_ticket_lead_department.name = ?))'
            . ' WHERE sub_ticket.subject = ?))',
            $select,
            ['y', 'n', 'lead', 'Engineering', 'x']
        );
    }

    public function testModelVisibilityFilterColumnsAreNotValidated()
    {
        // Unlike relation filters, a model's visibility filter is not validated against selectable columns;
        // its columns are qualified and emitted as-is (the model is trusted to reference actual columns).
        $model = new class () extends RestrictedGroup {
            public function createVisibilityFilter(Filter\Chain $filter): void
            {
                $filter->add(Filter::equal('not_a_column', 1));
            }
        };

        $query = (new Query())
            ->columns('name')
            ->setModel($model);

        $this->assertSql(
            'SELECT restricted_group.name FROM restricted_group WHERE restricted_group.not_a_column = ?',
            $query->assembleSelect(),
            [1]
        );
    }
}
