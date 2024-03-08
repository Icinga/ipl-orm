<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Query;
use ipl\Sql\Connection;
use ipl\Sql\Test\Databases;
use ipl\Stdlib\Filter;
use ipl\Tests\Orm\Lib\Model\Office;
use PHPUnit\Framework\TestCase;

/**
 * This test covers several cases of filter combinations where each represents an equivalence class, which are defined
 * as follows:
 *
 * a) Number of conditions: single
 *  b) Logical Operators: -, NOT
 *   f) Comparisons: affirmation, negation
 * a) Number of conditions: multiple
 *  b) Logical Operators: AND, OR, NOT(AND), NOT(OR)
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
 *
 * @todo NOT(OR) cases are missing
 */
class RelationFilterTest extends TestCase
{
    use Databases;

    /**
     * Search for offices where Donald works
     *
     * @equivalenceClass a:single, b:-, f:affirmation
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
            ['Donald']
        )->fetchAll();

        $this->assertSame('London', $offices[0]['city'] ?? 'not found');
        $this->assertSame('Amsterdam', $offices[1]['city'] ?? 'not found');
        $this->assertSame('Berlin', $offices[2]['city'] ?? 'not found');
        $this->assertSame('Baghdad', $offices[3]['city'] ?? 'not found');
        $this->assertSame(4, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::equal('employee.name', 'Donald'));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('London', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('Amsterdam', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('Berlin', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame('Baghdad', $results[3]['city'] ?? 'not found', $sql);
        $this->assertSame(4, count($results), $sql);
    }

    /**
     * Search for offices where Donald doesn't work
     *
     * @equivalenceClass a:single, b:-, f:negation
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
            ['Donald']
        )->fetchAll();

        $this->assertSame('New York', $offices[0]['city'] ?? 'not found');
        $this->assertSame('Cuxhaven', $offices[1]['city'] ?? 'not found');
        $this->assertSame('Sydney', $offices[2]['city'] ?? 'not found');
        $this->assertSame(3, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::unequal('employee.name', 'Donald'));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('New York', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('Cuxhaven', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('Sydney', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame(3, count($results), $sql);
    }

    /**
     * Search for offices where Donald doesn't work
     *
     * @equivalenceClass a:single, b:NOT(AND), f:affirmation
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testSingleAffirmativeConditionWithNotOperator(Connection $db)
    {
        $this->createOfficesAndEmployees($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e on e.office_id = office.id'
            . ' WHERE NOT (e.name = ?)'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['Donald']
        )->fetchAll();

        $this->assertSame('London', $offices[0]['city'] ?? 'not found');
        $this->assertSame('New York', $offices[1]['city'] ?? 'not found');
        $this->assertSame('Berlin', $offices[2]['city'] ?? 'not found');
        $this->assertSame('Sydney', $offices[3]['city'] ?? 'not found');
        $this->assertSame('Baghdad', $offices[4]['city'] ?? 'not found');
        $this->assertSame(5, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::none(
                Filter::equal('employee.name', 'Donald')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('London', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('New York', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('Berlin', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame('Sydney', $results[3]['city'] ?? 'not found', $sql);
        $this->assertSame('Baghdad', $results[4]['city'] ?? 'not found', $sql);
        $this->assertSame(5, count($results), $sql);
    }

    /**
     * Search for offices where Donald works
     *
     * @equivalenceClass a:single, b:NOT(AND), f:negation
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testSingleNegativeConditionWithNotOperator(Connection $db)
    {
        $this->createOfficesAndEmployees($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e on e.office_id = office.id'
            . ' WHERE NOT (e.name != ? OR e.name IS NULL)'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['Donald']
        )->fetchAll();

        $this->assertSame('London', $offices[0]['city'] ?? 'not found');
        $this->assertSame('Amsterdam', $offices[1]['city'] ?? 'not found');
        $this->assertSame('Berlin', $offices[2]['city'] ?? 'not found');
        $this->assertSame('Baghdad', $offices[3]['city'] ?? 'not found');
        $this->assertSame(4, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::none(
                Filter::unequal('employee.name', 'Donald')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('London', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('Amsterdam', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('Berlin', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame('Baghdad', $results[3]['city'] ?? 'not found', $sql);
        $this->assertSame(4, count($results), $sql);
    }

    /**
     * Search for offices where either Donald or Huey works
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
            ['Donald', 'Huey']
        )->fetchAll();

        $this->assertSame('London', $offices[0]['city'] ?? 'not found');
        $this->assertSame('Amsterdam', $offices[1]['city'] ?? 'not found');
        $this->assertSame('Berlin', $offices[2]['city'] ?? 'not found');
        $this->assertSame('Sydney', $offices[3]['city'] ?? 'not found');
        $this->assertSame('Baghdad', $offices[4]['city'] ?? 'not found');
        $this->assertSame(5, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::any(
                Filter::equal('employee.name', 'Donald'),
                Filter::equal('employee.name', 'Huey')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('London', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('Amsterdam', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('Berlin', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame('Sydney', $results[3]['city'] ?? 'not found', $sql);
        $this->assertSame('Baghdad', $results[4]['city'] ?? 'not found', $sql);
        $this->assertSame(5, count($results), $sql);
    }

    /**
     * Search for offices where either Donald or Huey doesn't work
     *
     * @equivalenceClass a:multiple, b:OR, c:single, d:same, e:same, f:negation
     * @dataProvider databases
     * @todo Finds what {@see testAndChainTargetingASingleRelationColumnWithTheSameNegativeOperator}
     *       does. The exact opposite. Should they really be equal? They are in monitoring, but there
     *       the results are the other way round. (What ALL(!=) finds here, cannot be found there)
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
            . ' WHERE e1.id IS NULL OR e2.id IS NULL'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['Donald', 'Huey']
        )->fetchAll();

        $this->assertSame('Amsterdam', $offices[0]['city'] ?? 'not found');
        $this->assertSame('New York', $offices[1]['city'] ?? 'not found');
        $this->assertSame('Cuxhaven', $offices[2]['city'] ?? 'not found');
        $this->assertSame('Sydney', $offices[3]['city'] ?? 'not found');
        $this->assertSame(4, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::any(
                Filter::unequal('employee.name', 'Donald'),
                Filter::unequal('employee.name', 'Huey')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('Amsterdam', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('New York', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('Cuxhaven', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame('Sydney', $results[3]['city'] ?? 'not found', $sql);
        $this->assertSame(4, count($results), $sql);
    }

    /**
     * Search for offices where Donald works or Huey doesn't
     *
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
            ['Donald', 'Huey']
        )->fetchAll();

        $this->assertSame('London', $offices[0]['city'] ?? 'not found');
        $this->assertSame('Amsterdam', $offices[1]['city'] ?? 'not found');
        $this->assertSame('New York', $offices[2]['city'] ?? 'not found');
        $this->assertSame('Berlin', $offices[3]['city'] ?? 'not found');
        $this->assertSame('Cuxhaven', $offices[4]['city'] ?? 'not found');
        $this->assertSame('Baghdad', $offices[5]['city'] ?? 'not found');
        $this->assertSame(6, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::any(
                Filter::equal('employee.name', 'Donald'),
                Filter::unequal('employee.name', 'Huey')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('London', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('Amsterdam', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('New York', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame('Berlin', $results[3]['city'] ?? 'not found', $sql);
        $this->assertSame('Cuxhaven', $results[4]['city'] ?? 'not found', $sql);
        $this->assertSame('Baghdad', $results[5]['city'] ?? 'not found', $sql);
        $this->assertSame(6, count($results), $sql);
    }

    /**
     * Search for offices where both Donald and Huey work
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
            ['Donald', 'Huey']
        )->fetchAll();

        $this->assertSame('London', $offices[0]['city'] ?? 'not found');
        $this->assertSame('Berlin', $offices[1]['city'] ?? 'not found');
        $this->assertSame('Baghdad', $offices[2]['city'] ?? 'not found');
        $this->assertSame(3, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::all(
                Filter::equal('employee.name', 'Donald'),
                Filter::equal('employee.name', 'Huey')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('London', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('Berlin', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('Baghdad', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame(3, count($results), $sql);
    }

    /**
     * Search for offices where neither Donald nor Huey work
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
            ['Donald', 'Huey']
        )->fetchAll();

        $this->assertSame('New York', $offices[0]['city'] ?? 'not found');
        $this->assertSame('Cuxhaven', $offices[1]['city'] ?? 'not found');
        $this->assertSame(2, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::all(
                Filter::unequal('employee.name', 'Donald'),
                Filter::unequal('employee.name', 'Huey')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('New York', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('Cuxhaven', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame(2, count($results), $sql);
    }

    /**
     * Search for offices where Donald works and Huey doesn't
     *
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
            ['Donald', 'Huey']
        )->fetchAll();

        $this->assertSame('Amsterdam', $offices[0]['city'] ?? 'not found');
        $this->assertSame(1, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::all(
                Filter::equal('employee.name', 'Donald'),
                Filter::unequal('employee.name', 'Huey')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('Amsterdam', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame(1, count($results), $sql);
    }

    /**
     * Search for offices where either Donald or Huey doesn't work
     *
     * @equivalenceClass a:multiple, b:NOT(AND), c:single, d:same, e:same, f:affirmation
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
            . ' WHERE NOT (e1.id IS NOT NULL AND e2.id IS NOT NULL)'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['Donald', 'Huey']
        )->fetchAll();

        $this->assertSame('Amsterdam', $offices[0]['city'] ?? 'not found');
        $this->assertSame('New York', $offices[1]['city'] ?? 'not found');
        $this->assertSame('Cuxhaven', $offices[2]['city'] ?? 'not found');
        $this->assertSame('Sydney', $offices[3]['city'] ?? 'not found');
        $this->assertSame(4, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::none(Filter::all(
                Filter::equal('employee.name', 'Donald'),
                Filter::equal('employee.name', 'Huey')
            )));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('Amsterdam', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('New York', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('Cuxhaven', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame('Sydney', $results[3]['city'] ?? 'not found', $sql);
        $this->assertSame(4, count($results), $sql);
    }

    /**
     * Search for offices where Donald and Huey work
     *
     * @equivalenceClass a:multiple, b:NOT(AND), c:single, d:same, e:same, f:negation
     * @dataProvider databases
     * @todo Finds the exact opposite of {@see testAndChainTargetingASingleRelationColumnWithTheSameNegativeOperator}.
     *       No wonder, actually. But monitoring doesn't behave the same way :'(
     *
     * @param Connection $db
     */
    public function testNotChainTargetingASingleRelationColumnWithTheSameNegativeOperator(Connection $db)
    {
        $this->createOfficesAndEmployees($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' WHERE office.id IN ('
            . '  SELECT office.id FROM office'
            . '  LEFT JOIN employee e on e.office_id = office.id'
            . '  WHERE e.name = ? OR e.name = ?'
            . '  GROUP BY office.id'
            . '  HAVING COUNT(e.id) >= 2'
            . ' )'
            . ' ORDER BY office.id',
            ['Donald', 'Huey']
        )->fetchAll();

        $this->assertSame('London', $offices[0]['city'] ?? 'not found');
        $this->assertSame('Berlin', $offices[1]['city'] ?? 'not found');
        $this->assertSame('Baghdad', $offices[2]['city'] ?? 'not found');
        $this->assertSame(3, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::none(Filter::all(
                Filter::unequal('employee.name', 'Donald'),
                Filter::unequal('employee.name', 'Huey')
            )));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('London', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('Berlin', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('Baghdad', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame(3, count($results), $sql);
    }

    /**
     * Search for offices where either Donald doesn't work or Huey does
     *
     * @equivalenceClass a:multiple, b:NOT(AND), c:single, d:same, e:different
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
            . ' WHERE NOT (e1.id IS NOT NULL AND e2.id IS NULL)'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['Donald', 'Huey']
        )->fetchAll();

        $this->assertSame('London', $offices[0]['city'] ?? 'not found');
        $this->assertSame('New York', $offices[1]['city'] ?? 'not found');
        $this->assertSame('Berlin', $offices[2]['city'] ?? 'not found');
        $this->assertSame('Cuxhaven', $offices[3]['city'] ?? 'not found');
        $this->assertSame('Sydney', $offices[4]['city'] ?? 'not found');
        $this->assertSame('Baghdad', $offices[5]['city'] ?? 'not found');
        $this->assertSame(6, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::none(Filter::all(
                Filter::equal('employee.name', 'Donald'),
                Filter::unequal('employee.name', 'Huey')
            )));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('London', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('New York', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('Berlin', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame('Cuxhaven', $results[3]['city'] ?? 'not found', $sql);
        $this->assertSame('Sydney', $results[4]['city'] ?? 'not found', $sql);
        $this->assertSame('Baghdad', $results[5]['city'] ?? 'not found', $sql);
        $this->assertSame(6, count($results), $sql);
    }

    /**
     * Search for offices where Huey works but not as manager
     *
     * @equivalenceClass a:multiple, b:AND, c:single, d:different, e:different
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testAndChainTargetingASingleRelationButDifferentColumnsWithDifferentOperators(Connection $db)
    {
        $this->createOfficesAndEmployees($db);

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::all(
                Filter::equal('employee.name', 'Huey'),
                Filter::unequal('employee.role', 'Manager')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('Sydney', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('Baghdad', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame(2, count($results), $sql);
    }

    /**
     * Search for offices where Donald works as accountant
     *
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
            ['Donald', 'Accountant']
        )->fetchAll();

        $this->assertSame('London', $offices[0]['city'] ?? 'not found');
        $this->assertSame('Berlin', $offices[1]['city'] ?? 'not found');
        $this->assertSame(2, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::all(
                Filter::equal('employee.name', 'Donald'),
                Filter::equal('employee.role', 'Accountant')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('London', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('Berlin', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame(2, count($results), $sql);
    }

    /**
     * Search for offices where Donald works or someone else not as accountant
     *
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
            ['Donald', 'Accountant']
        )->fetchAll();

        $this->assertSame('London', $offices[0]['city'] ?? 'not found');
        $this->assertSame('Amsterdam', $offices[1]['city'] ?? 'not found');
        $this->assertSame('New York', $offices[2]['city'] ?? 'not found');
        $this->assertSame('Berlin', $offices[3]['city'] ?? 'not found');
        $this->assertSame('Cuxhaven', $offices[4]['city'] ?? 'not found');
        $this->assertSame('Sydney', $offices[5]['city'] ?? 'not found');
        $this->assertSame('Baghdad', $offices[6]['city'] ?? 'not found');
        $this->assertSame(7, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::any(
                Filter::equal('employee.name', 'Donald'),
                Filter::unequal('employee.role', 'Accountant')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('London', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('Amsterdam', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('New York', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame('Berlin', $results[3]['city'] ?? 'not found', $sql);
        $this->assertSame('Cuxhaven', $results[4]['city'] ?? 'not found', $sql);
        $this->assertSame('Sydney', $results[5]['city'] ?? 'not found', $sql);
        $this->assertSame('Baghdad', $results[6]['city'] ?? 'not found', $sql);
        $this->assertSame(7, count($results), $sql);
    }

    /**
     * Search for offices where either Donald works or any other assistant
     *
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
            ['Donald', 'Assistant']
        )->fetchAll();

        $this->assertSame('London', $offices[0]['city'] ?? 'not found');
        $this->assertSame('Amsterdam', $offices[1]['city'] ?? 'not found');
        $this->assertSame('Berlin', $offices[2]['city'] ?? 'not found');
        $this->assertSame('Sydney', $offices[3]['city'] ?? 'not found');
        $this->assertSame('Baghdad', $offices[4]['city'] ?? 'not found');
        $this->assertSame(5, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::any(
                Filter::equal('employee.name', 'Donald'),
                Filter::equal('employee.role', 'Assistant')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('London', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('Amsterdam', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('Berlin', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame('Sydney', $results[3]['city'] ?? 'not found', $sql);
        $this->assertSame('Baghdad', $results[4]['city'] ?? 'not found', $sql);
        $this->assertSame(5, count($results), $sql);
    }

    /**
     * Search for offices where not just Huey works or only as a manager
     *
     * @equivalenceClass a:multiple, b:NOT(AND), c:single, d:different, e:different
     * @dataProvider databases
     * @todo Finds the exact opposite of {@see testAndChainTargetingASingleRelationButDifferentColumnsWithDifferentOperators}.
     *       No wonder, actually. But monitoring doesn't behave the same way :'(
     *
     * @param Connection $db
     */
    public function testNotChainTargetingASingleRelationButDifferentColumnsWithDifferentOperators(Connection $db)
    {
        $this->createOfficesAndEmployees($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e ON e.office_id = office.id'
            . ' WHERE NOT (e.name = ? AND e.role != ?)'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['Huey', 'Manager']
        )->fetchAll();

        $this->assertSame('London', $offices[0]['city'] ?? 'not found');
        $this->assertSame('Amsterdam', $offices[1]['city'] ?? 'not found');
        $this->assertSame('New York', $offices[2]['city'] ?? 'not found');
        $this->assertSame('Berlin', $offices[3]['city'] ?? 'not found');
        $this->assertSame('Baghdad', $offices[4]['city'] ?? 'not found');
        $this->assertSame(5, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::none(Filter::all(
                Filter::equal('employee.name', 'Huey'),
                Filter::unequal('employee.role', 'Manager')
            )));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('London', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('Amsterdam', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('New York', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame('Berlin', $results[3]['city'] ?? 'not found', $sql);
        $this->assertSame('Baghdad', $results[4]['city'] ?? 'not found', $sql);
        $this->assertSame(5, count($results), $sql);
    }

    /**
     * Search for offices where not just Donald works or not as manager
     *
     * @equivalenceClass a:multiple, b:NOT(AND), c:single, d:different, e:same
     * @dataProvider databases
     * @todo Finds Cuxhaven instead of Baghdad. Not wrong, actually. Why does monitoring behave differently?
     *
     * @param Connection $db
     */
    public function testNotChainTargetingASingleRelationButDifferentColumnsWithTheSameOperator(Connection $db)
    {
        $this->createOfficesAndEmployees($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' LEFT JOIN employee e ON e.office_id = office.id'
            . ' WHERE NOT (e.name = ? AND e.role = ?)'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['Donald', 'Manager']
        )->fetchAll();

        $this->assertSame('London', $offices[0]['city'] ?? 'not found');
        $this->assertSame('Amsterdam', $offices[1]['city'] ?? 'not found');
        $this->assertSame('New York', $offices[2]['city'] ?? 'not found');
        $this->assertSame('Berlin', $offices[3]['city'] ?? 'not found');
        $this->assertSame('Sydney', $offices[4]['city'] ?? 'not found');
        $this->assertSame('Baghdad', $offices[5]['city'] ?? 'not found');
        $this->assertSame(6, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::none(Filter::all(
                Filter::equal('employee.name', 'Donald'),
                Filter::equal('employee.role', 'Manager')
            )));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('London', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('Amsterdam', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('New York', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame('Berlin', $results[3]['city'] ?? 'not found', $sql);
        $this->assertSame('Sydney', $results[4]['city'] ?? 'not found', $sql);
        $this->assertSame('Baghdad', $results[5]['city'] ?? 'not found', $sql);
        $this->assertSame(6, count($results), $sql);
    }

    /**
     * Search for offices where Donald works in the accounting department
     *
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
            ['Donald', 'Accounting']
        )->fetchAll();

        $this->assertSame('London', $offices[0]['city'] ?? 'not found');
        $this->assertSame('Barcelona', $offices[1]['city'] ?? 'not found');
        $this->assertSame(2, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::all(
                Filter::equal('employee.name', 'Donald'),
                Filter::equal('employee.department.name', 'Accounting')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('London', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('Barcelona', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame(2, count($results), $sql);
    }

    /**
     * Search for offices where Donald doesn't work in the accounting department
     *
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
            . ' WHERE office.id NOT IN ('
            . '  SELECT office.id FROM office'
            . '  LEFT JOIN employee e on e.office_id = office.id'
            . '  LEFT JOIN department d on e.department_id = d.id'
            . '  WHERE e.name = ? AND d.name = ?'
            . '  GROUP BY office.id'
            . ' )'
            . ' ORDER BY office.id',
            ['Donald', 'Accounting']
        )->fetchAll();

        $this->assertSame('Berlin', $offices[0]['city'] ?? 'not found');
        $this->assertSame('Amsterdam', $offices[1]['city'] ?? 'not found');
        $this->assertSame('New York', $offices[2]['city'] ?? 'not found');
        $this->assertSame('Paris', $offices[3]['city'] ?? 'not found');
        $this->assertSame(4, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::all(
                Filter::unequal('employee.name', 'Donald'),
                Filter::unequal('employee.department.name', 'Accounting')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('Berlin', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('Amsterdam', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('New York', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame('Paris', $results[3]['city'] ?? 'not found', $sql);
        $this->assertSame(4, count($results), $sql);
    }

    /**
     * Search for offices where Donald works but not in the accounting department
     *
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
            ['Donald', 'Accounting']
        )->fetchAll();

        $this->assertSame('Berlin', $offices[0]['city'] ?? 'not found');
        $this->assertSame(1, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::all(
                Filter::equal('employee.name', 'Donald'),
                Filter::unequal('employee.department.name', 'Accounting')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('Berlin', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame(1, count($results), $sql);
    }

    /**
     * Search for offices where either Donald works or someone else in the accounting department
     *
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
            ['Donald', 'Accounting']
        )->fetchAll();

        $this->assertSame('London', $offices[0]['city'] ?? 'not found');
        $this->assertSame('Berlin', $offices[1]['city'] ?? 'not found');
        $this->assertSame('New York', $offices[2]['city'] ?? 'not found');
        $this->assertSame('Paris', $offices[3]['city'] ?? 'not found');
        $this->assertSame('Barcelona', $offices[4]['city'] ?? 'not found');
        $this->assertSame(5, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::any(
                Filter::equal('employee.name', 'Donald'),
                Filter::equal('employee.department.name', 'Accounting')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('London', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('Berlin', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('New York', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame('Paris', $results[3]['city'] ?? 'not found', $sql);
        $this->assertSame('Barcelona', $results[4]['city'] ?? 'not found', $sql);
        $this->assertSame(5, count($results), $sql);
    }

    /**
     * Search for offices where Mickey doesn't work or no-one in the accounting department
     *
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
            . ' WHERE office.id NOT IN ('
            . '  SELECT office.id FROM office'
            . '  LEFT JOIN employee e on e.office_id = office.id'
            . '  LEFT JOIN department d on e.department_id = d.id'
            . '  WHERE e.name = ?'
            . '  GROUP BY office.id'
            . ' ) OR office.id NOT IN ('
            . '  SELECT office.id FROM office'
            . '  LEFT JOIN employee e on e.office_id = office.id'
            . '  LEFT JOIN department d on e.department_id = d.id'
            . '  WHERE d.name = ?'
            . '  GROUP BY office.id'
            . ' )'
            . ' ORDER BY office.id',
            ['Mickey', 'Accounting']
        )->fetchAll();

        $this->assertSame('London', $offices[0]['city'] ?? 'not found');
        $this->assertSame('Berlin', $offices[1]['city'] ?? 'not found');
        $this->assertSame('Amsterdam', $offices[2]['city'] ?? 'not found');
        $this->assertSame('Barcelona', $offices[3]['city'] ?? 'not found');
        $this->assertSame(4, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::any(
                Filter::unequal('employee.name', 'Mickey'),
                Filter::unequal('employee.department.name', 'Accounting')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('London', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('Berlin', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('Amsterdam', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame('Barcelona', $results[3]['city'] ?? 'not found', $sql);
        $this->assertSame(4, count($results), $sql);
    }

    /**
     * Search for offices where either Donald works or no-one in the accounting department
     *
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
            . ' WHERE office.id IN ('
            . '  SELECT office.id FROM office'
            . '  LEFT JOIN employee e on e.office_id = office.id'
            . '  WHERE e.name = ?'
            . '  GROUP BY office.id'
            . ' ) OR office.id NOT IN ('
            . '  SELECT office.id FROM office'
            . '  LEFT JOIN employee e on e.office_id = office.id'
            . '  LEFT JOIN department d on e.department_id = d.id'
            . '  WHERE d.name = ?'
            . '  GROUP BY office.id'
            . ' )'
            . ' ORDER BY office.id',
            ['Donald', 'Accounting']
        )->fetchAll();

        $this->assertSame('London', $offices[0]['city'] ?? 'not found');
        $this->assertSame('Berlin', $offices[1]['city'] ?? 'not found');
        $this->assertSame('Amsterdam', $offices[2]['city'] ?? 'not found');
        $this->assertSame('Barcelona', $offices[3]['city'] ?? 'not found');
        $this->assertSame(4, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::any(
                Filter::equal('employee.name', 'Donald'),
                Filter::unequal('employee.department.name', 'Accounting')
            ));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('London', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('Berlin', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('Amsterdam', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame('Barcelona', $results[3]['city'] ?? 'not found', $sql);
        $this->assertSame(4, count($results), $sql);
    }

    /**
     * Search for offices where Donald doesn't work in the accounting department
     *
     * @equivalenceClass a:multiple, b:NOT(AND), c:multiple, e:same, f:affirmation
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testNotChainTargetingMultipleRelationsWithTheSameAffirmativeOperator(Connection $db)
    {
        $this->createOfficesEmployeesAndDepartments($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' WHERE office.id NOT IN ('
            . '  SELECT office.id FROM office'
            . '  LEFT JOIN employee e on e.office_id = office.id'
            . '  LEFT JOIN department d on e.department_id = d.id'
            . '  WHERE e.name = ? AND d.name = ?'
            . '  GROUP BY office.id'
            . ' )'
            . ' ORDER BY office.id',
            ['Donald', 'Accounting']
        )->fetchAll();

        $this->assertSame('Berlin', $offices[0]['city'] ?? 'not found');
        $this->assertSame('Amsterdam', $offices[1]['city'] ?? 'not found');
        $this->assertSame('New York', $offices[2]['city'] ?? 'not found');
        $this->assertSame('Paris', $offices[3]['city'] ?? 'not found');
        $this->assertSame(4, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::none(Filter::all(
                Filter::equal('employee.name', 'Donald'),
                Filter::equal('employee.department.name', 'Accounting')
            )));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('Berlin', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('Amsterdam', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('New York', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame('Paris', $results[3]['city'] ?? 'not found', $sql);
        $this->assertSame(4, count($results), $sql);
    }

    /**
     * Search for offices where no Donald doesn't work not in the accounting department
     *
     * Or for your convenience: Where Donald works
     *
     * @equivalenceClass a:multiple, b:NOT(AND), c:multiple, e:same, f:negation
     * @dataProvider databases
     * @todo Just take a look at the POC and wonder how that would ever be automatically generated.
     *       Especially if you consider adding another comparison into the mix. This is way too
     *       complex for the ORM to handle let alone for an ordinary user to understand.
     *
     * @param Connection $db
     */
    public function testNotChainTargetingMultipleRelationsWithTheSameNegativeOperator(Connection $db)
    {
        $this->createOfficesEmployeesAndDepartments($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' WHERE NOT (office.id NOT IN ('
            . '  SELECT office.id FROM office'
            . '  LEFT JOIN employee e on e.office_id = office.id'
            . '  WHERE e.name = ?'
            . '  GROUP BY office.id'
            . ' ) AND office.id NOT IN ('
            . '  SELECT office.id FROM office'
            . '  LEFT JOIN employee e on e.office_id = office.id'
            . '  LEFT JOIN department d on e.department_id = d.id'
            . '  WHERE e.name = ? AND d.name = ?'
            . '  GROUP BY office.id'
            . ' ))'
            . ' ORDER BY office.id',
            ['Donald', 'Donald', 'Accounting']
        )->fetchAll();

        $this->assertSame('London', $offices[0]['city'] ?? 'not found');
        $this->assertSame('Berlin', $offices[1]['city'] ?? 'not found');
        $this->assertSame('Barcelona', $offices[2]['city'] ?? 'not found');
        $this->assertSame(3, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::none(Filter::all(
                Filter::unequal('employee.name', 'Donald'),
                Filter::unequal('employee.department.name', 'Accounting')
            )));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('London', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('Berlin', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('Barcelona', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame(3, count($results), $sql);
    }

    /**
     * Search for offices where Donald doesn't work or in the accounting department
     *
     * @equivalenceClass a:multiple, b:NOT(AND), c:multiple, e:different
     * @dataProvider databases
     *
     * @param Connection $db
     */
    public function testNotChainTargetingMultipleRelationsWithDifferentOperators(Connection $db)
    {
        $this->createOfficesEmployeesAndDepartments($db);

        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' WHERE office.id NOT IN ('
            . '  SELECT office.id FROM office'
            . '  LEFT JOIN employee e on e.office_id = office.id'
            . '  LEFT JOIN department d on e.department_id = d.id'
            . '  WHERE e.name = ? AND d.name != ?'
            . '  GROUP BY office.id'
            . ' )'
            . ' ORDER BY office.id',
            ['Donald', 'Accounting']
        )->fetchAll();

        $this->assertSame('London', $offices[0]['city'] ?? 'not found');
        $this->assertSame('Amsterdam', $offices[1]['city'] ?? 'not found');
        $this->assertSame('New York', $offices[2]['city'] ?? 'not found');
        $this->assertSame('Paris', $offices[3]['city'] ?? 'not found');
        $this->assertSame('Barcelona', $offices[4]['city'] ?? 'not found');
        $this->assertSame(5, count($offices));

        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::none(Filter::all(
                Filter::equal('employee.name', 'Donald'),
                Filter::unequal('employee.department.name', 'Accounting')
            )));
        $results = iterator_to_array($offices);
        $sql = $this->getSql($offices);

        $this->assertSame('London', $results[0]['city'] ?? 'not found', $sql);
        $this->assertSame('Amsterdam', $results[1]['city'] ?? 'not found', $sql);
        $this->assertSame('New York', $results[2]['city'] ?? 'not found', $sql);
        $this->assertSame('Paris', $results[3]['city'] ?? 'not found', $sql);
        $this->assertSame('Barcelona', $results[4]['city'] ?? 'not found', $sql);
        $this->assertSame(5, count($results), $sql);
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
        $db->insert('office', ['id' => 1, 'city' => 'London']);
        $db->insert('department', ['id' => 1, 'name' => 'Accounting']);
        $db->insert('department', ['id' => 2, 'name' => 'Kitchen']);
        $db->insert('employee', ['id' => 1, 'department_id' => 1, 'name' => 'Minnie', 'role' => 'CEO']); // remote
        $db->insert(
            'employee',
            ['id' => 2, 'department_id' => 2, 'office_id' => 1, 'name' => 'Goofy', 'role' => 'Developer']
        );

        // This POC uses inner joins to achieve the desired result
        $offices = $db->prepexec(
            'SELECT office.city FROM office'
            . ' INNER JOIN employee e on e.office_id = office.id'
            . ' INNER JOIN department d on e.department_id = d.id'
            . ' WHERE d.name != ?'
            . ' GROUP BY office.id'
            . ' ORDER BY office.id',
            ['Accounting']
        )->fetchAll();

        $this->assertSame('London', $offices[0]['city'] ?? 'not found');

        // The ORM will use a NOT IN and needs to ignore false positives explicitly
        $offices = Office::on($db)
            ->columns(['office.city'])
            ->orderBy('office.id')
            ->filter(Filter::unequal('employee.department.name', 'Accounting'));
        $results = iterator_to_array($offices);

        $this->assertSame('London', $results[0]['city'] ?? 'not found', $this->getSql($offices));
    }

    /**
     * Create a definite set of permutations with employees and offices
     *
     * Employee | Role         | City
     * -------- | ------------ | ---------
     * Donald   | Accountant   | London
     * Huey     | Manager      | London
     * Donald   | Salesperson  | Amsterdam
     * Dewey    | Manager      | New York
     * Donald   | Accountant   | Berlin
     * Huey     | Manager      | Berlin
     * Louie    | Cook         | Berlin
     * -        | -            | Cuxhaven
     * Huey     | Assistant    | Sydney
     * Donald   | Manager      | Baghdad
     * Huey     | Accountant   | Baghdad
     *
     * @param Connection $db
     */
    protected function createOfficesAndEmployees(Connection $db)
    {
        $db->insert('office', ['id' => 1, 'city' => 'London']); // Two
        $db->insert('employee', ['id' => 1, 'office_id' => 1, 'name' => 'Donald', 'role' => 'Accountant']);
        $db->insert('employee', ['id' => 2, 'office_id' => 1, 'name' => 'Huey', 'role' => 'Manager']);
        $db->insert('office', ['id' => 2, 'city' => 'Amsterdam']); // One of the two
        $db->insert('employee', ['id' => 3, 'office_id' => 2, 'name' => 'Donald', 'role' => 'Salesperson']);
        $db->insert('office', ['id' => 3, 'city' => 'New York']); // None of the two
        $db->insert('employee', ['id' => 4, 'office_id' => 3, 'name' => 'Dewey', 'role' => 'Manager']);
        $db->insert('office', ['id' => 4, 'city' => 'Berlin']); // The two plus another one
        $db->insert('employee', ['id' => 5, 'office_id' => 4, 'name' => 'Donald', 'role' => 'Accountant']);
        $db->insert('employee', ['id' => 6, 'office_id' => 4, 'name' => 'Huey', 'role' => 'Manager']);
        $db->insert('employee', ['id' => 7, 'office_id' => 4, 'name' => 'Louie', 'role' => 'Cook']);
        $db->insert('office', ['id' => 5, 'city' => 'Cuxhaven']); // None
        $db->insert('office', ['id' => 6, 'city' => 'Sydney']); // The other one of the two
        $db->insert('employee', ['id' => 8, 'office_id' => 6, 'name' => 'Huey', 'role' => 'Assistant']);
        $db->insert('office', ['id' => 7, 'city' => 'Baghdad']); // The two with switched roles
        $db->insert('employee', ['id' => 9, 'office_id' => 7, 'name' => 'Donald', 'role' => 'Manager']);
        $db->insert('employee', ['id' => 10, 'office_id' => 7, 'name' => 'Huey', 'role' => 'Accountant']);
    }

    /**
     * Create a definite set of permutations with employees, offices and departments
     *
     * Employee | Role         | Department | City
     * -------- | ------------ | ---------- | ---------
     * Donald   | Accountant   | Accounting | London
     * Dewey    | Cook         | Kitchen    | London
     * Donald   | Salesperson  | Kitchen    | Berlin
     * Huey     | Assistant    | Accounting | Berlin
     * Huey     | QA Lead      | Kitchen    | Amsterdam
     * Mickey   | Manager      | Accounting | New York
     * Mickey   | Cook         | Kitchen    | Paris
     * Louie    | Accountant   | Accounting | Paris
     * Donald   | Accountant   | Accounting | Barcelona
     *
     * @param Connection $db
     */
    protected function createOfficesEmployeesAndDepartments(Connection $db)
    {
        $db->insert('office', ['id' => 1, 'city' => 'London']);
        $db->insert('office', ['id' => 2, 'city' => 'Berlin']);
        $db->insert('office', ['id' => 3, 'city' => 'Amsterdam']);
        $db->insert('office', ['id' => 4, 'city' => 'New York']);
        $db->insert('office', ['id' => 5, 'city' => 'Paris']);
        $db->insert('office', ['id' => 6, 'city' => 'Barcelona']);
        $db->insert('department', ['id' => 1, 'name' => 'Accounting']);
        $db->insert('department', ['id' => 2, 'name' => 'Kitchen']);
        $db->insert(
            'employee',
            ['id' => 1, 'office_id' => 1, 'department_id' => 1, 'name' => 'Donald', 'role' => 'Accountant']
        );
        $db->insert(
            'employee',
            ['id' => 2, 'office_id' => 1, 'department_id' => 2, 'name' => 'Dewey', 'role' => 'Cook']
        );
        $db->insert(
            'employee',
            ['id' => 3, 'office_id' => 2, 'department_id' => 2, 'name' => 'Donald', 'role' => 'Salesperson']
        );
        $db->insert(
            'employee',
            ['id' => 4, 'office_id' => 2, 'department_id' => 1, 'name' => 'Huey', 'role' => 'Assistant']
        );
        $db->insert(
            'employee',
            ['id' => 5, 'office_id' => 3, 'department_id' => 2, 'name' => 'Huey', 'role' => 'QA Lead']
        );
        $db->insert(
            'employee',
            ['id' => 6, 'office_id' => 4, 'department_id' => 1, 'name' => 'Mickey', 'role' => 'Manager']
        );
        $db->insert(
            'employee',
            ['id' => 7, 'office_id' => 5, 'department_id' => 2, 'name' => 'Mickey', 'role' => 'Cook']
        );
        $db->insert(
            'employee',
            ['id' => 8, 'office_id' => 5, 'department_id' => 1, 'name' => 'Louie', 'role' => 'Accountant']
        );
        $db->insert(
            'employee',
            ['id' => 9, 'office_id' => 6, 'department_id' => 1, 'name' => 'Donald', 'role' => 'Accountant']
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
