<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Query;
use ipl\Sql\Connection;
use ipl\Sql\Test\Databases;
use ipl\Stdlib\Filter;
use ipl\Tests\Orm\Lib\Model\Department;
use ipl\Tests\Orm\Lib\Model\Office;
use PHPUnit\Framework\TestCase;

/**
 * This test covers several cases of filter combinations where each represents an equivalence class, which are defined
 * as follows:
 *
 * a) Number of conditions: single
 *  f) Comparisons: affirmation, negation
 * a) Number of conditions: multiple
 *  b) Logical Operators: AND, OR, NOT
 *   c) Number of relations: single
 *    d) Columns: same
 *     e) Operators: same
 *      f) Comparisons: affirmation, negation
 *     e) Operators: different
 *    d) Columns: different
 *     e) Operators: same, different
 *   c) Number of relations: multiple
 *    e) Operators: same, different
 *     f) Comparisons: affirmation, negation
 *
 * If a test covers such a case, it is marked with the corresponding variables.
 *
 * The tests rely on a few assumptions which are the same for all of them:
 * - All filters target a to-many relation
 * - The ORM only differs between negative and affirmative filters, hence why only equal and unequal filters are used
 * - Negation comparisons only return results if no such matches are made, instead of including results that have other
 *   non-matches ("Not any of those" instead of "Any other than those")
 *
 *  Every test contains at least one proof of concept using a manually crafted SQL query. (Not necessarily the same way
 *  the ORM constructs it) Such must not fail. If they do, the dataset has changed. The ORM query must return the same
 *  results.
 */
class RelationFilterTest extends TestCase
{
    use Databases;

    /**
     * @equivalenceClass a:single, f:affirmation
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testSingleAffirmativeCondition(Connection $db)
    {
        $this->createOfficesAndEmployees($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e on e.office_id = office.id'
            . ' WHERE e.name = ?'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo']
        )->fetchAll();

        $this->assertSame('foo', $offices[0]['city'] ?? 'not found');
        $this->assertSame('bar', $offices[1]['city'] ?? 'not found');
        $this->assertSame('oof', $offices[2]['city'] ?? 'not found');
        $this->assertSame(3, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::equal('employee.name', 'foo'));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('foo', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('bar', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('oof', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame(3, count($results), $sql);
    }

    /**
     * @equivalenceClass a:single, f:negation
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testSingleNegativeCondition(Connection $db)
    {
        $this->createOfficesAndEmployees($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e on e.office_id = office.id AND e.name = ?'
            . ' WHERE e.id IS NULL'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo']
        )->fetchAll();

        $this->assertSame('baz', $offices[0]['city'] ?? 'not found');
        $this->assertSame('qux', $offices[1]['city'] ?? 'not found');
        $this->assertSame('quux', $offices[2]['city'] ?? 'not found');
        $this->assertSame(3, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::unequal('employee.name', 'foo'));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('baz', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('qux', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('quux', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame(3, count($results), $sql);
    }

    /**
     * Test whether multiple equal filters combined with OR on the same column of
     * the same to-many relation, include results that match any condition.
     *
     * @equivalenceClass a:multiple, b:OR, c:single, d:same, e:same, f:affirmation
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testOrChainTargetingASingleRelationColumnWithTheSameAffirmativeOperator(Connection $db)
    {
        $this->createOfficesAndEmployees($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e1 on e1.office_id = office.id AND e1.name = ?'
            . ' LEFT JOIN employee e2 on e2.office_id = office.id AND e2.name = ?'
            . ' WHERE e1.id IS NOT NULL OR e2.id IS NOT NULL'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo', 'bar']
        )->fetchAll();

        $this->assertSame('foo', $offices[0]['city'] ?? 'not found');
        $this->assertSame('bar', $offices[1]['city'] ?? 'not found');
        $this->assertSame('oof', $offices[2]['city'] ?? 'not found');
        $this->assertSame('quux', $offices[3]['city'] ?? 'not found');
        $this->assertSame(4, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::any(
                Filter::equal('employee.name', 'foo'),
                Filter::equal('employee.name', 'bar')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('foo', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('bar', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('oof', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame('quux', $results[3]['city'] ?? 'not found', $sql);
        $this->assertSame(4, count($results), $sql);
    }

    /**
     * Test whether multiple unequal filters combined with OR on the same column
     * of the same to-many relation, filter out results that match all conditions.
     *
     * @equivalenceClass a:multiple, b:OR, c:single, d:same, e:same, f:negation
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testOrChainTargetingASingleRelationColumnWithTheSameNegativeOperator(Connection $db)
    {
        $this->createOfficesAndEmployees($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e1 on e1.office_id = office.id AND e1.name = ?'
            . ' LEFT JOIN employee e2 on e2.office_id = office.id AND e2.name = ?'
            . ' WHERE NOT (e1.id IS NOT NULL AND e2.id IS NOT NULL)'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo', 'bar']
        )->fetchAll();

        $this->assertSame('bar', $offices[0]['city'] ?? 'not found');
        $this->assertSame('baz', $offices[1]['city'] ?? 'not found');
        $this->assertSame('qux', $offices[2]['city'] ?? 'not found');
        $this->assertSame('quux', $offices[3]['city'] ?? 'not found');
        $this->assertSame(4, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::any(
                Filter::unequal('employee.name', 'foo'),
                Filter::unequal('employee.name', 'bar')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('bar', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('baz', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('qux', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame('quux', $results[3]['city'] ?? 'not found', $sql);
        $this->assertSame(4, count($results), $sql);
    }

    /**
     * @equivalenceClass a:multiple, b:OR, c:single, d:same, e:different
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testOrChainTargetingASingleRelationColumnWithDifferentOperators(Connection $db)
    {
        $this->createOfficesAndEmployees($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e1 on e1.office_id = office.id AND e1.name = ?'
            . ' LEFT JOIN employee e2 on e2.office_id = office.id AND e2.name = ?'
            . ' WHERE e1.id IS NOT NULl OR e2.id IS NULL'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo', 'bar']
        )->fetchAll();

        $this->assertSame('foo', $offices[0]['city'] ?? 'not found');
        $this->assertSame('bar', $offices[1]['city'] ?? 'not found');
        $this->assertSame('baz', $offices[2]['city'] ?? 'not found');
        $this->assertSame('oof', $offices[3]['city'] ?? 'not found');
        $this->assertSame('qux', $offices[4]['city'] ?? 'not found');
        $this->assertSame(5, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::any(
                Filter::equal('employee.name', 'foo'),
                Filter::unequal('employee.name', 'bar')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('foo', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('bar', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('baz', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame('oof', $results[3]['city'] ?? 'not found', $sql);
        $this->assertSame('qux', $results[4]['city'] ?? 'not found', $sql);
        $this->assertSame(5, count($results), $sql);
    }

    /**
     * Test whether multiple equal filters combined with AND on the same column of
     * the same to-many relation, only include results that match all conditions.
     *
     * @equivalenceClass a:multiple, b:AND, c:single, d:same, e:same, f:affirmation
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testAndChainTargetingASingleRelationColumnWithTheSameAffirmativeOperator(Connection $db)
    {
        $this->createOfficesAndEmployees($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e1 on e1.office_id = office.id AND e1.name = ?'
            . ' LEFT JOIN employee e2 on e2.office_id = office.id AND e2.name = ?'
            . ' WHERE e1.id IS NOT NULL AND e2.id IS NOT NULL'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo', 'bar']
        )->fetchAll();

        $this->assertSame('foo', $offices[0]['city'] ?? 'not found');
        $this->assertSame('oof', $offices[1]['city'] ?? 'not found');
        $this->assertSame(2, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::all(
                Filter::equal('employee.name', 'foo'),
                Filter::equal('employee.name', 'bar')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('foo', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('oof', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame(2, count($results), $sql);
    }

    /**
     * Test whether multiple unequal filters combined with AND on the same column
     * of the same to-many relation, filter out results that match any condition.
     *
     * @equivalenceClass a:multiple, b:AND, c:single, d:same, e:same, f:negation
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testAndChainTargetingASingleRelationColumnWithTheSameNegativeOperator(Connection $db)
    {
        $this->createOfficesAndEmployees($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e1 on e1.office_id = office.id AND e1.name = ?'
            . ' LEFT JOIN employee e2 on e2.office_id = office.id AND e2.name = ?'
            . ' WHERE e1.id IS NULL AND e2.id IS NULL'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo', 'bar']
        )->fetchAll();

        $this->assertSame('baz', $offices[0]['city'] ?? 'not found');
        $this->assertSame('qux', $offices[1]['city'] ?? 'not found');
        $this->assertSame(2, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::all(
                Filter::unequal('employee.name', 'foo'),
                Filter::unequal('employee.name', 'bar')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('baz', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('qux', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame(2, count($results), $sql);
    }

    /**
     * @equivalenceClass a:multiple, b:AND, c:single, d:same, e:different
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testAndChainTargetingASingleRelationColumnWithDifferentOperators(Connection $db)
    {
        $this->createOfficesAndEmployees($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e1 on e1.office_id = office.id AND e1.name = ?'
            . ' LEFT JOIN employee e2 on e2.office_id = office.id AND e2.name = ?'
            . ' WHERE e1.id IS NOT NULL AND e2.id IS NULL'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo', 'bar']
        )->fetchAll();

        $this->assertSame('bar', $offices[0]['city'] ?? 'not found');
        $this->assertSame(1, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::all(
                Filter::equal('employee.name', 'foo'),
                Filter::unequal('employee.name', 'bar')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('bar', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame(1, count($results), $sql);
    }

    /**
     * Test whether multiple equal filters combined with NOT on the same column of
     * the same to-many relation, include results that match none of the conditions.
     *
     * @equivalenceClass a:multiple, b:NOT, c:single, d:same, e:same, f:affirmation
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testNotChainTargetingASingleRelationColumnWithTheSameAffirmativeOperator(Connection $db)
    {
        $this->createOfficesAndEmployees($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e1 on e1.office_id = office.id AND e1.name = ?'
            . ' LEFT JOIN employee e2 on e2.office_id = office.id AND e2.name = ?'
            . ' WHERE NOT (e1.id IS NOT NULL OR e2.id IS NOT NULL)'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo', 'bar']
        )->fetchAll();

        $this->assertSame('baz', $offices[0]['city'] ?? 'not found');
        $this->assertSame('qux', $offices[1]['city'] ?? 'not found');
        $this->assertSame(2, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::none(
                Filter::equal('employee.name', 'foo'),
                Filter::equal('employee.name', 'bar')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('baz', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('qux', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame(2, count($results), $sql);
    }

    /**
     * Test whether multiple unequal filters combined with NOT on the same column
     * of the same to-many relation, only include results that match all conditions.
     *
     * @equivalenceClass a:multiple, b:NOT, c:single, d:same, e:same, f:negation
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testNotChainTargetingASingleRelationColumnWithTheSameNegativeOperator(Connection $db)
    {
        $this->createOfficesAndEmployees($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e1 on e1.office_id = office.id AND e1.name = ?'
            . ' LEFT JOIN employee e2 on e2.office_id = office.id AND e2.name = ?'
            . ' WHERE NOT (e1.id IS NULL OR e2.id IS NULL)'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo', 'bar']
        )->fetchAll();

        $this->assertSame('foo', $offices[0]['city'] ?? 'not found');
        $this->assertSame('oof', $offices[1]['city'] ?? 'not found');
        $this->assertSame(2, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::none(
                Filter::unequal('employee.name', 'foo'),
                Filter::unequal('employee.name', 'bar')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('foo', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('oof', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame(2, count($results), $sql);
    }

    /**
     * @equivalenceClass a:multiple, b:NOT, c:single, d:same, e:different
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testNotChainTargetingASingleRelationColumnWithDifferentOperators(Connection $db)
    {
        $this->createOfficesAndEmployees($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e1 on e1.office_id = office.id AND e1.name = ?'
            . ' LEFT JOIN employee e2 on e2.office_id = office.id AND e2.name = ?'
            . ' WHERE NOT (e1.id IS NOT NULL OR e2.id IS NULL)'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo', 'bar']
        )->fetchAll();

        $this->assertSame('quux', $offices[0]['city'] ?? 'not found');
        $this->assertSame(1, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::none(
                Filter::equal('employee.name', 'foo'),
                Filter::unequal('employee.name', 'bar')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('quux', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame(1, count($results), $sql);
    }

    /**
     * Test whether the ORM produces correct results if an unequal filter is combined
     * with an equal filter on the same 1-n relation and both with different columns
     *
     * @equivalenceClass a:multiple, b:AND, c:single, d:different, e:different
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testAndChainTargetingASingleRelationButDifferentColumnsWithDifferentOperators(Connection $db)
    {
        $db->insert('department', ['id' => 1, 'name' => 'foo']);
        $db->insert('employee', ['id' => 1, 'department_id' => 1, 'name' => 'foo', 'role' => 'bar']);
        $db->insert('employee', ['id' => 2, 'department_id' => 1, 'name' => 'bar', 'role' => 'foo']);
        $db->insert('department', ['id' => 2, 'name' => 'bar']);
        $db->insert('employee', ['id' => 5, 'department_id' => 2, 'name' => 'foo', 'role' => 'oof']);
        $db->insert('department', ['id' => 3, 'name' => 'baz']);
        $db->insert('employee', ['id' => 6, 'department_id' => 3, 'name' => 'foo', 'role' => null]);
        $db->insert('employee', ['id' => 7, 'department_id' => 3, 'name' => 'bar', 'role' => null]);
        $db->insert('department', ['id' => 4, 'name' => 'qux']);
        $db->insert('employee', ['id' => 8, 'department_id' => 4, 'name' => 'foo', 'role' => 'bar']);
        $db->insert('employee', ['id' => 9, 'department_id' => 4, 'name' => 'foo', 'role' => 'baz']);

        // First a proof of concept by using a manually crafted SQL query
        $departments = $db->prepexec(
            'SELECT department.name FROM department'
            . ' LEFT JOIN employee e on department.id = e.department_id'
            . ' WHERE e.name = ? AND (e.role != ? OR e.role IS NULL)'
            . ' GROUP BY department.id'
            . ' ORDER BY department.id',
            ['foo', 'bar']
        )->fetchAll();

        $this->assertSame('bar', $departments[0]['name'] ?? 'not found');
        $this->assertSame('baz', $departments[1]['name'] ?? 'not found');
        $this->assertSame('qux', $departments[2]['name'] ?? 'not found');
        $this->assertSame(3, count($departments));

        // Now let's do the same using the ORM
        $departments = Department::on($db)
            ->columns(['department.name'])
            ->orderBy('department.id')
            ->filter(Filter::all(
                Filter::equal('employee.name', 'foo'),
                Filter::unequal('employee.role', 'bar')
            ));
        $results = iterator_to_array($departments);
        $sql = $this->getSql($departments);

        $this->assertSame('bar', $results[0]['name'] ?? 'not found', $sql);
        $this->assertSame('baz', $results[1]['name'] ?? 'not found', $sql);
        $this->assertSame('qux', $results[2]['name'] ?? 'not found', $sql);
        $this->assertSame(3, count($results), $sql);

        // The ORM may perform fine till now, but let's see what happens if we include some false positives
        $db->insert('department', ['id' => 5, 'name' => 'quux']);
        $db->insert('employee', ['id' => 10, 'department_id' => 5, 'name' => 'bar', 'role' => 'oof']);

        // This employee's role doesn't match but the name does neither, resulting in the department not showing up
        $db->insert('employee', ['id' => 11, 'department_id' => 5, 'name' => 'oof', 'role' => 'foo']);

        // This department has no employees and as such none with the desired name, although the role, being not
        // set due to the left join, would match. It might also show up due to a NOT EXISTS/NOT IN.
        $db->insert('department', ['id' => 6, 'name' => 'qa']);

        // Proof of concept first, again
        $departments = $db->prepexec(
            'SELECT department.name FROM department'
            . ' LEFT JOIN employee e on department.id = e.department_id'
            . ' WHERE e.name = ? AND (e.role != ? OR e.role IS NULL)'
            . ' GROUP BY department.id'
            . ' ORDER BY department.id',
            ['bar', 'foo']
        )->fetchAll();

        $this->assertSame('baz', $departments[0]['name'] ?? 'not found');
        $this->assertSame('quux', $departments[1]['name'] ?? 'not found');
        $this->assertSame(2, count($departments));

        // Now the ORM. Note that the result depends on how the subqueries are constructed to filter the results
        $departments = Department::on($db)
            ->columns(['department.name'])
            ->orderBy('department.id')
            ->filter(Filter::all(
                Filter::equal('employee.name', 'bar'),
                Filter::unequal('employee.role', 'foo')
            ));
        $results = iterator_to_array($departments);
        $sql = $this->getSql($departments);

        $this->assertSame('baz', $results[0]['name'] ?? 'not found', $sql);
        $this->assertSame('quux', $results[1]['name'] ?? 'not found', $sql);
        $this->assertSame(2, count($results), $sql);
    }

    /**
     * @equivalenceClass a:multiple, b:AND, c:single, d:different, e:same
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testAndChainTargetingASingleRelationButDifferentColumnsWithTheSameOperator(Connection $db)
    {
        $this->createOfficesAndEmployees($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e ON e.office_id = office.id'
            . ' WHERE e.name = ? AND e.role = ?'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo', 'bar']
        )->fetchAll();

        $this->assertSame('foo', $offices[0]['city'] ?? 'not found');
        $this->assertSame('oof', $offices[1]['city'] ?? 'not found');
        $this->assertSame(2, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::all(
                Filter::equal('employee.name', 'foo'),
                Filter::equal('employee.role', 'bar')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('foo', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('oof', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame(2, count($results), $sql);
    }

    /**
     * @equivalenceClass a:multiple, b:OR, c:single, d:different, e:different
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testOrChainTargetingASingleRelationButDifferentColumnsWithDifferentOperators(Connection $db)
    {
        $this->createOfficesAndEmployees($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e ON e.office_id = office.id'
            . ' WHERE e.name = ? OR (e.role != ? OR e.role IS NULL)'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo', 'bar']
        )->fetchAll();

        $this->assertSame('foo', $offices[0]['city'] ?? 'not found');
        $this->assertSame('bar', $offices[1]['city'] ?? 'not found');
        $this->assertSame('baz', $offices[2]['city'] ?? 'not found');
        $this->assertSame('oof', $offices[3]['city'] ?? 'not found');
        $this->assertSame('qux', $offices[4]['city'] ?? 'not found');
        $this->assertSame('quux', $offices[5]['city'] ?? 'not found');
        $this->assertSame(6, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::any(
                Filter::equal('employee.name', 'foo'),
                Filter::unequal('employee.role', 'bar')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('foo', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('bar', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('baz', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame('oof', $results[3]['city'] ?? 'not found', $sql);
        $this->assertSame('qux', $results[4]['city'] ?? 'not found', $sql);
        $this->assertSame('quux', $results[5]['city'] ?? 'not found', $sql);
        $this->assertSame(6, count($results), $sql);
    }

    /**
     * @equivalenceClass a:multiple, b:OR, c:single, d:different, e:same
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testOrChainTargetingASingleRelationButDifferentColumnsWithTheSameOperator(Connection $db)
    {
        $this->createOfficesAndEmployees($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e ON e.office_id = office.id'
            . ' WHERE e.name = ? OR e.role = ?'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo', 'bar']
        )->fetchAll();

        $this->assertSame('foo', $offices[0]['city'] ?? 'not found');
        $this->assertSame('bar', $offices[1]['city'] ?? 'not found');
        $this->assertSame('oof', $offices[2]['city'] ?? 'not found');
        $this->assertSame(3, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::any(
                Filter::equal('employee.name', 'foo'),
                Filter::equal('employee.role', 'bar')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('foo', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('bar', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('oof', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame(3, count($results), $sql);
    }

    /**
     * @equivalenceClass a:multiple, b:NOT, c:single, d:different, e:different
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testNotChainTargetingASingleRelationButDifferentColumnsWithDifferentOperators(Connection $db)
    {
        $this->createOfficesAndEmployees($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e ON e.office_id = office.id'
            . ' WHERE NOT (e.name = ? OR (e.role != ? OR e.role IS NULL))'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo', 'qux']
        )->fetchAll();

        $this->assertSame('baz', $offices[0]['city'] ?? 'not found');
        $this->assertSame(1, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::none(
                Filter::equal('employee.name', 'foo'),
                Filter::unequal('employee.role', 'qux')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('baz', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame(1, count($results), $sql);
    }

    /**
     * @equivalenceClass a:multiple, b:NOT, c:single, d:different, e:same
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testNotChainTargetingASingleRelationButDifferentColumnsWithTheSameOperator(Connection $db)
    {
        $this->createOfficesAndEmployees($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e ON e.office_id = office.id'
            . ' WHERE NOT (e.name = ? OR e.role = ?) OR e.id IS NULL'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo', 'bar']
        )->fetchAll();

        $this->assertSame('foo', $offices[0]['city'] ?? 'not found');
        $this->assertSame('baz', $offices[1]['city'] ?? 'not found');
        $this->assertSame('oof', $offices[2]['city'] ?? 'not found');
        $this->assertSame('qux', $offices[3]['city'] ?? 'not found');
        $this->assertSame('quux', $offices[4]['city'] ?? 'not found');
        $this->assertSame(5, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::none(
                Filter::equal('employee.name', 'foo'),
                Filter::equal('employee.role', 'bar')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('foo', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('baz', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('oof', $offices[2]['city'] ?? 'not found', $sql);
        $this->assertSame('qux', $results[3]['city'] ?? 'not found', $sql);
        $this->assertSame('quux', $results[4]['city'] ?? 'not found', $sql);
        $this->assertSame(5, count($results), $sql);
    }

    /**
     * @equivalenceClass a:multiple, b:AND, c:multiple, e:same, f:affirmation
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testAndChainTargetingMultipleRelationsWithTheSameAffirmativeOperator(Connection $db)
    {
        $this->createOfficesEmployeesAndDepartments($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e on e.office_id = office.id'
            . ' LEFT JOIN department d on e.department_id = d.id'
            . ' WHERE e.name = ? AND d.name = ?'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo', 'bar']
        )->fetchAll();

        $this->assertSame('foo', $offices[0]['city'] ?? 'not found');
        $this->assertSame(1, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::all(
                Filter::equal('employee.name', 'foo'),
                Filter::equal('employee.department.name', 'bar')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('foo', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame(1, count($results), $sql);
    }

    /**
     * @equivalenceClass a:multiple, b:AND, c:multiple, e:same, f:negation
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testAndChainTargetingMultipleRelationsWithTheSameNegativeOperator(Connection $db)
    {
        $this->createOfficesEmployeesAndDepartments($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e on e.office_id = office.id AND e.name = ?'
            . ' LEFT JOIN department d on e.department_id = d.id AND d.name = ?'
            . ' WHERE e.id IS NULL AND d.id IS NULL'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo', 'bar']
        )->fetchAll();

        $this->assertSame('bar', $offices[0]['city'] ?? 'not found');
        $this->assertSame('baz', $offices[1]['city'] ?? 'not found');
        $this->assertSame(2, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::all(
                Filter::unequal('employee.name', 'foo'),
                Filter::unequal('employee.department.name', 'bar')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('bar', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('baz', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame(2, count($results), $sql);
    }

    /**
     * @equivalenceClass a:multiple, b:AND, c:multiple, e:different
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testAndChainTargetingMultipleRelationsWithDifferentOperators(Connection $db)
    {
        $this->createOfficesEmployeesAndDepartments($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e on e.office_id = office.id'
            . ' LEFT JOIN department d on e.department_id = d.id'
            . ' WHERE e.name = ? AND d.name != ?'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo', 'bar']
        )->fetchAll();

        $this->assertSame('oof', $offices[0]['city'] ?? 'not found');
        $this->assertSame(1, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::all(
                Filter::equal('employee.name', 'foo'),
                Filter::unequal('employee.department.name', 'bar')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('oof', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame(1, count($results), $sql);
    }

    /**
     * @equivalenceClass a:multiple, b:OR, c:multiple, e:same, f:affirmation
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testOrChainTargetingMultipleRelationsWithTheSameAffirmativeOperator(Connection $db)
    {
        $this->createOfficesEmployeesAndDepartments($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e on e.office_id = office.id'
            . ' LEFT JOIN department d on e.department_id = d.id'
            . ' WHERE e.name = ? OR d.name = ?'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo', 'bar']
        )->fetchAll();

        $this->assertSame('foo', $offices[0]['city'] ?? 'not found');
        $this->assertSame('oof', $offices[1]['city'] ?? 'not found');
        $this->assertSame('baz', $offices[2]['city'] ?? 'not found');
        $this->assertSame(3, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::any(
                Filter::equal('employee.name', 'foo'),
                Filter::equal('employee.department.name', 'bar')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('foo', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('oof', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('baz', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame(3, count($results), $sql);
    }

    /**
     * @equivalenceClass a:multiple, b:OR, c:multiple, e:same, f:negation
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testOrChainTargetingMultipleRelationsWithTheSameNegativeOperator(Connection $db)
    {
        $this->createOfficesEmployeesAndDepartments($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e on e.office_id = office.id AND e.name = ?'
            . ' LEFT JOIN department d on e.department_id = d.id AND d.name = ?'
            . ' WHERE e.id IS NULL OR d.id IS NULL'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo', 'bar']
        )->fetchAll();

        $this->assertSame('oof', $offices[0]['city'] ?? 'not found');
        $this->assertSame('bar', $offices[1]['city'] ?? 'not found');
        $this->assertSame('baz', $offices[2]['city'] ?? 'not found');
        $this->assertSame(3, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::any(
                Filter::unequal('employee.name', 'foo'),
                Filter::unequal('employee.department.name', 'bar')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('oof', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('bar', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('baz', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame(3, count($results), $sql);
    }

    /**
     * @equivalenceClass a:multiple, b:OR, c:multiple, e:different
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testOrChainTargetingMultipleRelationsWithDifferentOperators(Connection $db)
    {
        $this->createOfficesEmployeesAndDepartments($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e on e.office_id = office.id'
            . ' LEFT JOIN department d on e.department_id = d.id'
            . ' WHERE e.name = ? OR d.name != ?'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo', 'bar']
        )->fetchAll();

        $this->assertSame('foo', $offices[0]['city'] ?? 'not found');
        $this->assertSame('oof', $offices[1]['city'] ?? 'not found');
        $this->assertSame('bar', $offices[2]['city'] ?? 'not found');
        $this->assertSame(3, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::any(
                Filter::equal('employee.name', 'foo'),
                Filter::unequal('employee.department.name', 'bar')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('foo', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('oof', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('bar', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame(3, count($results), $sql);
    }

    /**
     * @equivalenceClass a:multiple, b:NOT, c:multiple, e:same, f:affirmation
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testNotChainTargetingMultipleRelationsWithTheSameAffirmativeOperator(Connection $db)
    {
        $this->createOfficesEmployeesAndDepartments($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e on e.office_id = office.id'
            . ' LEFT JOIN department d on e.department_id = d.id'
            . ' WHERE NOT (e.name = ? OR d.name = ?)'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo', 'bar']
        )->fetchAll();

        $this->assertSame('foo', $offices[0]['city'] ?? 'not found');
        $this->assertSame('bar', $offices[1]['city'] ?? 'not found');
        $this->assertSame(2, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::none(
                Filter::equal('employee.name', 'foo'),
                Filter::equal('employee.department.name', 'bar')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('foo', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('bar', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame(2, count($results), $sql);
    }

    /**
     * @equivalenceClass a:multiple, b:NOT, c:multiple, e:same, f:negation
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testNotChainTargetingMultipleRelationsWithTheSameNegativeOperator(Connection $db)
    {
        $this->createOfficesEmployeesAndDepartments($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e on e.office_id = office.id AND e.name = ?'
            . ' LEFT JOIN department d on e.department_id = d.id AND d.name = ?'
            . ' WHERE NOT (e.id IS NULL OR d.id IS NULL)'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo', 'bar']
        )->fetchAll();

        $this->assertSame('foo', $offices[0]['city'] ?? 'not found');
        $this->assertSame(1, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::none(
                Filter::unequal('employee.name', 'foo'),
                Filter::unequal('employee.department.name', 'bar')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('foo', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame(1, count($results), $sql);
    }

    /**
     * @equivalenceClass a:multiple, b:NOT, c:multiple, e:different
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testNotChainTargetingMultipleRelationsWithDifferentOperators(Connection $db)
    {
        $this->createOfficesEmployeesAndDepartments($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e on e.office_id = office.id'
            . ' LEFT JOIN department d on e.department_id = d.id'
            . ' WHERE NOT (e.name = ? OR d.name != ?)'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['foo', 'bar']
        )->fetchAll();

        $this->assertSame('oof', $offices[0]['city'] ?? 'not found');
        $this->assertSame('baz', $offices[1]['city'] ?? 'not found');
        $this->assertSame(2, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::none(
                Filter::equal('employee.name', 'foo'),
                Filter::unequal('employee.department.name', 'bar')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('oof', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('baz', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame(2, count($results), $sql);
    }

    /**
     * Test whether an unequal, that targets a to-many relation to which a link can only be established through an
     * optional other relation, is built by the ORM in a way that coincidental matches are ignored
     *
     * This will fail if the ORM generates a NOT IN which uses a subquery that produces NULL values.
     *
     * @dataProvider databases
     */
    public function testUnequalTargetingAnOptionalToManyRelationIgnoresFalsePositives(Connection $db)
    {
        $db->insert('office', ['id' => 1, 'city' => 'foo']);
        $db->insert('department', ['id' => 1, 'name' => 'bar']);
        $db->insert('department', ['id' => 2, 'name' => 'baz']);
        $db->insert('employee', ['id' => 1, 'department_id' => 1, 'name' => 'qux', 'role' => 'quux']); // remote
        $db->insert(
            'employee',
            ['id' => 2, 'department_id' => 2, 'office_id' => 1, 'name' => 'corge', 'role' => 'grault']
        );

        // This POC uses inner joins to achieve the desired result
        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' INNER JOIN employee e on e.office_id = office.id'
            . ' INNER JOIN department d on e.department_id = d.id'
            . ' WHERE d.name != ?'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['bar']
        )->fetchAll();

        $this->assertSame('foo', $offices[0]['city'] ?? 'not found');

        // The ORM will use a NOT IN and needs to ignore false positives explicitly
        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::unequal('employee.department.name', 'bar'));
        $results = iterator_to_array($offices);

        $this->assertSame('foo', $results[0]['city'] ?? 'not found', $this->getSql($offices));
    }

    /**
     * Create a definite set of permutations with employees and offices
     *
     * @param Connection $db
     */
    protected function createOfficesAndEmployees(Connection $db)
    {
        $db->insert('office', ['id' => 1, 'city' => 'foo']); // Two
        $db->insert('employee', ['id' => 1, 'office_id' => 1, 'name' => 'foo', 'role' => 'bar']);
        $db->insert('employee', ['id' => 2, 'office_id' => 1, 'name' => 'bar', 'role' => 'foo']);
        $db->insert('office', ['id' => 2, 'city' => 'bar']); // One of the two
        $db->insert('employee', ['id' => 3, 'office_id' => 2, 'name' => 'foo', 'role' => 'oof']);
        $db->insert('office', ['id' => 3, 'city' => 'baz']); // None of the two
        $db->insert('employee', ['id' => 4, 'office_id' => 3, 'name' => 'oof', 'role' => 'qux']);
        $db->insert('office', ['id' => 4, 'city' => 'oof']); // The two plus another one
        $db->insert('employee', ['id' => 5, 'office_id' => 4, 'name' => 'foo', 'role' => 'bar']);
        $db->insert('employee', ['id' => 6, 'office_id' => 4, 'name' => 'bar', 'role' => 'foo']);
        $db->insert('employee', ['id' => 7, 'office_id' => 4, 'name' => 'quux', 'role' => 'corge']);
        $db->insert('office', ['id' => 5, 'city' => 'qux']); // None
        $db->insert('office', ['id' => 6, 'city' => 'quux']); // The other one of the two
        $db->insert('employee', ['id' => 8, 'office_id' => 6, 'name' => 'bar', 'role' => 'baz']);
    }

    /**
     * Create a definite set of permutations with employees, offices and departments
     *
     * @param Connection $db
     */
    protected function createOfficesEmployeesAndDepartments(Connection $db)
    {
        $db->insert('office', ['id' => 1, 'city' => 'foo']);
        $db->insert('office', ['id' => 2, 'city' => 'oof']);
        $db->insert('office', ['id' => 3, 'city' => 'bar']);
        $db->insert('office', ['id' => 4, 'city' => 'baz']);
        $db->insert('department', ['id' => 1, 'name' => 'bar']);
        $db->insert('department', ['id' => 2, 'name' => 'baz']);
        $db->insert(
            'employee',
            ['id' => 1, 'office_id' => 1, 'department_id' => 1, 'name' => 'foo', 'role' => 'bar']
        );
        $db->insert(
            'employee',
            ['id' => 2, 'office_id' => 1, 'department_id' => 2, 'name' => 'oof', 'role' => 'corge']
        );
        $db->insert(
            'employee',
            ['id' => 3, 'office_id' => 2, 'department_id' => 2, 'name' => 'foo', 'role' => 'oof']
        );
        $db->insert(
            'employee',
            ['id' => 4, 'office_id' => 2, 'department_id' => 1, 'name' => 'bar', 'role' => 'baz']
        );
        $db->insert(
            'employee',
            ['id' => 5, 'office_id' => 3, 'department_id' => 2, 'name' => 'bar', 'role' => 'qux']
        );
        $db->insert(
            'employee',
            ['id' => 6, 'office_id' => 4, 'department_id' => 1, 'name' => 'baz', 'role' => 'foo']
        );
    }

    protected function createSchema(Connection $db, string $driver): void
    {
        $db->exec('CREATE TABLE office (id INT PRIMARY KEY, city VARCHAR(255))');
        $db->exec('CREATE TABLE department (id INT PRIMARY KEY, name VARCHAR(255))');
        $db->exec(
            'CREATE TABLE employee (id INT PRIMARY KEY, department_id INT,'
            . ' office_id INT, name VARCHAR(255), role VARCHAR(255))'
        );
    }

    protected function dropSchema(Connection $db, string $driver): void
    {
        $db->exec('DROP TABLE IF EXISTS employee');
        $db->exec('DROP TABLE IF EXISTS department');
        $db->exec('DROP TABLE IF EXISTS office');
    }

    /**
     * Format the given query to SQL
     *
     * @param Query $query
     *
     * @return string
     */
    protected function getSql(Query $query): string
    {
        list($sql, $values) = $query->getDb()->getQueryBuilder()->assembleSelect($query->assembleSelect());
        foreach ($values as $value) {
            $pos = strpos($sql, '?');
            if ($pos !== false) {
                if (is_string($value)) {
                    $value = "'" . $value . "'";
                }

                $sql = substr_replace($sql, $value, $pos, 1);
            }
        }

        return $sql;
    }
}
