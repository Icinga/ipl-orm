<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Compat\FilterProcessor;
use ipl\Orm\Query;
use ipl\Sql\Adapter\Pgsql;
use ipl\Sql\Connection;
use ipl\Sql\Test\Databases;
use ipl\Sql\Test\SqlAssertions;
use ipl\Stdlib\Filter;
use ipl\Tests\Orm\Lib\Model\Employee;
use ipl\Tests\Orm\Lib\Model\Office;
use PHPUnit\Framework\Attributes\DataProvider;

class FilterProcessorTest extends \PHPUnit\Framework\TestCase
{
    use Databases;
    use SqlAssertions;

    protected function setUp(): void
    {
        $this->setUpSqlAssertions();
        $this->setUpDatabases();
    }

    public function testUnequalDoesNotOverrideUnlike()
    {
        $query = new Query();
        $query->setModel(new Car());
        $query->setDb(new TestConnection());

        $filter = Filter::all(
            Filter::unequal('passenger.name', 'foo'),
            Filter::unlike('passenger.gender', 'b*r')
        );

        FilterProcessor::apply($filter, $query);

        $where = $query->getSelectBase()->getWhere();

        $this->assertArrayHasKey(1, $where);
        $this->assertArrayHasKey(0, $where[1]);
        $this->assertArrayHasKey(1, $where[1][0]);
        $this->assertArrayHasKey(0, $where[1][0][1]);
        $this->assertArrayHasKey(1, $where[1][0][1][0]);
        $this->assertArrayHasKey('(car.id NOT IN (?) OR car.id IS NULL)', $where[1][0][1][0][1]);
        $this->assertSame(
            ['AND', [
                ['AND', [
                    ['OR', [
                        ['AND', [
                            ['AND', [
                                'sub_passenger.name = ?' => 'foo'
                            ]]
                        ]]
                    ]]
                ]],
                ['AND', [
                    'sub_passenger_car.id IS NOT NULL'
                ]]
            ]],
            $where[1][0][1][0][1]['(car.id NOT IN (?) OR car.id IS NULL)']->getWhere()
        );

        $this->assertArrayHasKey(1, $where[1][0][1]);
        $this->assertArrayHasKey(1, $where[1][0][1][1]);
        $this->assertArrayHasKey('(car.id NOT IN (?) OR car.id IS NULL)', $where[1][0][1][1][1]);
        $this->assertSame(
            ['AND', [
                ['AND', [
                    ['OR', [
                        ['AND', [
                            ['AND', [
                                'sub_passenger.gender LIKE ?' => 'b%r'
                            ]]
                        ]]
                    ]]
                ]],
                ['AND', [
                    'sub_passenger_car.id IS NOT NULL'
                ]]
            ]],
            $where[1][0][1][1][1]['(car.id NOT IN (?) OR car.id IS NULL)']->getWhere()
        );
    }

    /**
     * Test whether an unequal, that targets a to-many relation to which a link can only be established through an
     * optional other relation, is built by the ORM in a way that coincidental matches are ignored
     *
     * This will fail if the ORM generates a NOT IN which uses a subquery that produces NULL values.
     */
    #[DataProvider('databases')]
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

        $this->assertSame('London', $results[0]['city'] ?? 'not found');
    }

    #[DataProvider('databases')]
    public function testNegationOfAToManyRelationWorksAcrossDatabaseAdapters(Connection $db): void
    {
        $db->insert(
            'employee',
            ['id' => 1, 'department_id' => 1, 'name' => 'Minnie', 'role' => 'CEO', 'deleted' => 'n']
        );
        $db->insert(
            'employee',
            ['id' => 2, 'department_id' => 2, 'name' => 'Goofy', 'role' => 'Developer', 'deleted' => 'n']
        );
        $db->insert('chair', ['department_id' => 1, 'employee_id' => 1, 'vendor' => 'Acme Chairs']);
        $db->insert('chair', ['department_id' => 2, 'employee_id' => 1, 'vendor' => 'Bcme Chairs']);
        $db->insert('chair', ['department_id' => 3, 'employee_id' => 2, 'vendor' => 'Bcme Chairs']);

        $employeesWithoutAcmeChairs = Employee::on($db)
            ->filter(Filter::unequal('chair.vendor', 'Acme Chairs'));
        $results = iterator_to_array($employeesWithoutAcmeChairs);

        $this->assertCount(1, $results);
        $this->assertSame('Goofy', $results[0]->name);
    }

    #[DataProvider('databases')]
    public function testLikeWithASingleWildcardTargetingAToManyRelation(Connection $db): void
    {
        $this->insertEmployeesWithChairs($db);

        $employees = Employee::on($db)
            ->columns('employee.name')
            ->orderBy('employee.id')
            ->filter(Filter::like('chair.vendor', '*'));

        $results = iterator_to_array($employees);

        $this->assertCount(4, $results);
        $this->assertSame('Minnie', $results[0]->name);
        $this->assertSame('Goofy', $results[1]->name);
        $this->assertSame('Mickey', $results[2]->name);
        $this->assertSame('Scrooge', $results[3]->name);

        $sql = <<<SQL
SELECT employee.name
FROM employee
WHERE (employee.deleted = ?) AND (employee.id IN
    ((SELECT sub_chair_employee.id AS sub_chair_employee_id
    FROM chair sub_chair
    INNER JOIN employee sub_chair_employee
        ON (sub_chair_employee.id = sub_chair.employee_id) AND (sub_chair_employee.deleted = ?)
    WHERE sub_chair.vendor IS NOT NULL)))
ORDER BY employee.id
SQL;

        $this->assertSql(
            $sql,
            $employees->assembleSelect(),
            ['n', 'n']
        );
    }

    #[DataProvider('databases')]
    public function testUnlikeWithASingleWildcardTargetingAToManyRelation(Connection $db): void
    {
        $this->insertEmployeesWithChairs($db);

        $employees = Employee::on($db)
            ->columns('employee.name')
            ->orderBy('employee.id')
            ->filter(Filter::unlike('chair.vendor', '*'));

        $results = iterator_to_array($employees);

        $this->assertCount(2, $results);
        $this->assertSame('Pluto', $results[0]->name);
        $this->assertSame('Donald', $results[1]->name);

        if ($db->getAdapter() instanceof Pgsql) {
            $sql = <<<SQL
SELECT employee.name
FROM employee
WHERE (employee.deleted = ?) AND ((employee.id NOT IN
    ((SELECT sub_chair_employee.id AS sub_chair_employee_id
    FROM chair sub_chair
    INNER JOIN employee sub_chair_employee
        ON (sub_chair_employee.id = sub_chair.employee_id) AND (sub_chair_employee.deleted = ?)
    WHERE (sub_chair.vendor IS NOT NULL) AND (sub_chair_employee.id IS NOT NULL)
    GROUP BY sub_chair_employee.id
    HAVING COUNT(DISTINCT (sub_chair.department_id, sub_chair.employee_id)) >= ?))
  OR employee.id IS NULL))
ORDER BY employee.id
SQL;
        } else {
            $sql = <<<SQL
SELECT employee.name
FROM employee
WHERE (employee.deleted = ?) AND ((employee.id NOT IN
    ((SELECT sub_chair_employee.id AS sub_chair_employee_id
    FROM chair sub_chair
    INNER JOIN employee sub_chair_employee
        ON (sub_chair_employee.id = sub_chair.employee_id) AND (sub_chair_employee.deleted = ?)
    WHERE (sub_chair.vendor IS NOT NULL) AND (sub_chair_employee.id IS NOT NULL)
    GROUP BY sub_chair_employee.id
    HAVING COUNT(DISTINCT sub_chair.department_id, sub_chair.employee_id) >= ?))
  OR employee.id IS NULL))
ORDER BY employee.id
SQL;
        }

        $this->assertSql(
            $sql,
            $employees->assembleSelect(),
            ['n', 'n', 1]
        );
    }

    #[DataProvider('databases')]
    public function testUnlikeWithASingleWildcardCombinedWithAPositiveComparisonWorksAsBefore(Connection $db): void
    {
        $this->insertEmployeesWithChairs($db);

        $employees = Employee::on($db)
            ->columns('employee.name')
            ->orderBy('employee.id')
            ->filter(Filter::all(
                Filter::unlike('chair.vendor', '*'),
                Filter::equal('chair.department_id', 1)
            ));

        $results = iterator_to_array($employees);

        $this->assertCount(1, $results);
        $this->assertSame('Minnie', $results[0]->name);

        if ($db->getAdapter() instanceof Pgsql) {
            $sql = <<<SQL
SELECT employee.name
FROM employee
WHERE (employee.deleted = ?) AND (((employee.id NOT IN
    ((SELECT sub_chair_employee.id AS sub_chair_employee_id
      FROM chair sub_chair
      INNER JOIN employee sub_chair_employee ON (sub_chair_employee.id = sub_chair.employee_id)
        AND (sub_chair_employee.deleted = ?)
      WHERE ((sub_chair.vendor IS NOT NULL) AND (sub_chair.department_id = ?)) AND (sub_chair_employee.id IS NOT NULL)
      GROUP BY sub_chair_employee.id
      HAVING COUNT(DISTINCT (sub_chair.department_id, sub_chair.employee_id)) >= ?))
    OR employee.id IS NULL)) AND (employee.id IN
      ((SELECT sub_chair_employee.id AS sub_chair_employee_id
        FROM chair sub_chair
        INNER JOIN employee sub_chair_employee ON (sub_chair_employee.id = sub_chair.employee_id)
          AND (sub_chair_employee.deleted = ?)
        WHERE sub_chair.department_id = ?))))
ORDER BY employee.id
SQL;
        } else {
            $sql = <<<SQL
SELECT employee.name
FROM employee
WHERE (employee.deleted = ?) AND (((employee.id NOT IN
    ((SELECT sub_chair_employee.id AS sub_chair_employee_id
      FROM chair sub_chair
      INNER JOIN employee sub_chair_employee ON (sub_chair_employee.id = sub_chair.employee_id)
        AND (sub_chair_employee.deleted = ?)
      WHERE ((sub_chair.vendor IS NOT NULL) AND (sub_chair.department_id = ?)) AND (sub_chair_employee.id IS NOT NULL)
      GROUP BY sub_chair_employee.id
      HAVING COUNT(DISTINCT sub_chair.department_id, sub_chair.employee_id) >= ?))
    OR employee.id IS NULL)) AND (employee.id IN
      ((SELECT sub_chair_employee.id AS sub_chair_employee_id
        FROM chair sub_chair
        INNER JOIN employee sub_chair_employee ON (sub_chair_employee.id = sub_chair.employee_id)
          AND (sub_chair_employee.deleted = ?)
        WHERE sub_chair.department_id = ?))))
ORDER BY employee.id
SQL;
        }

        $this->assertSql(
            $sql,
            $employees->assembleSelect(),
            ['n', 'n', 1, 1, 'n', 1]
        );
    }

    #[DataProvider('databases')]
    public function testUnlikeCanBeTreatedAsPositiveComparison(Connection $db): void
    {
        $this->insertEmployeesWithChairs($db);

        $specialUnlike = Filter::unlike('chair.vendor', '*');
        $specialUnlike->metaData()->set('treat-positively', true);

        $employees = Employee::on($db)
            ->columns('employee.name')
            ->orderBy('employee.id')
            ->filter(Filter::all(
                $specialUnlike,
                Filter::equal('chair.department_id', 1)
            ));

        $results = iterator_to_array($employees);

        $this->assertCount(2, $results);
        $this->assertSame('Minnie', $results[0]->name);
        $this->assertSame('Scrooge', $results[1]->name);

        $sql = <<<SQL
SELECT employee.name
FROM employee
WHERE (employee.deleted = ?) AND (employee.id IN
      ((SELECT sub_chair_employee.id AS sub_chair_employee_id
        FROM chair sub_chair
        INNER JOIN employee sub_chair_employee ON (sub_chair_employee.id = sub_chair.employee_id)
          AND (sub_chair_employee.deleted = ?)
        WHERE (sub_chair.department_id = ?) AND (sub_chair.vendor IS NULL))))
ORDER BY employee.id
SQL;

        $this->assertSql(
            $sql,
            $employees->assembleSelect(),
            ['n', 'n', 1]
        );
    }

    #[DataProvider('databases')]
    public function testLikeWithASingleWildcardIsAppliedToNegatedSubQueries(Connection $db): void
    {
        $this->insertEmployeesWithChairs($db);

        $employees = Employee::on($db)
            ->columns('employee.name')
            ->orderBy('employee.id')
            ->filter(Filter::all(
                Filter::like('chair.vendor', '*'),
                Filter::unequal('chair.department_id', 1)
            ));

        $results = iterator_to_array($employees);

        $this->assertCount(1, $results);
        $this->assertSame('Minnie', $results[0]->name);

        if ($db->getAdapter() instanceof Pgsql) {
            $sql = <<<SQL
SELECT employee.name
FROM employee
WHERE (employee.deleted = ?) AND (((employee.id NOT IN
    ((SELECT sub_chair_employee.id AS sub_chair_employee_id
    FROM chair sub_chair
    INNER JOIN employee sub_chair_employee
        ON (sub_chair_employee.id = sub_chair.employee_id) AND (sub_chair_employee.deleted = ?)
    WHERE ((sub_chair.department_id = ?) AND (sub_chair.vendor IS NOT NULL)) AND (sub_chair_employee.id IS NOT NULL)
    GROUP BY sub_chair_employee.id
    HAVING COUNT(DISTINCT (sub_chair.department_id, sub_chair.employee_id)) >= ?))
  OR employee.id IS NULL)) AND (employee.id IN
    ((SELECT sub_chair_employee.id AS sub_chair_employee_id
    FROM chair sub_chair
    INNER JOIN employee sub_chair_employee
        ON (sub_chair_employee.id = sub_chair.employee_id) AND (sub_chair_employee.deleted = ?)
    WHERE sub_chair.vendor IS NOT NULL))))
ORDER BY employee.id
SQL;
        } else {
            $sql = <<<SQL
SELECT employee.name
FROM employee
WHERE (employee.deleted = ?) AND (((employee.id NOT IN
    ((SELECT sub_chair_employee.id AS sub_chair_employee_id
    FROM chair sub_chair
    INNER JOIN employee sub_chair_employee
        ON (sub_chair_employee.id = sub_chair.employee_id) AND (sub_chair_employee.deleted = ?)
    WHERE ((sub_chair.department_id = ?) AND (sub_chair.vendor IS NOT NULL)) AND (sub_chair_employee.id IS NOT NULL)
    GROUP BY sub_chair_employee.id
    HAVING COUNT(DISTINCT sub_chair.department_id, sub_chair.employee_id) >= ?))
  OR employee.id IS NULL)) AND (employee.id IN
    ((SELECT sub_chair_employee.id AS sub_chair_employee_id
    FROM chair sub_chair
    INNER JOIN employee sub_chair_employee
        ON (sub_chair_employee.id = sub_chair.employee_id) AND (sub_chair_employee.deleted = ?)
    WHERE sub_chair.vendor IS NOT NULL))))
ORDER BY employee.id
SQL;
        }

        $this->assertSql(
            $sql,
            $employees->assembleSelect(),
            ['n', 'n', 1, 1, 'n']
        );
    }

    #[DataProvider('databases')]
    public function testLikeWithASingleWildcardIsAppliedToOtherPositiveComparisons(Connection $db): void
    {
        $this->insertEmployeesWithChairs($db);

        $employees = Employee::on($db)
            ->columns('employee.name')
            ->orderBy('employee.id')
            ->filter(Filter::all(
                Filter::like('chair.vendor', '*'),
                Filter::equal('chair.department_id', 1)
            ));

        $results = iterator_to_array($employees);

        $this->assertCount(3, $results);
        $this->assertSame('Goofy', $results[0]->name);
        $this->assertSame('Mickey', $results[1]->name);
        $this->assertSame('Scrooge', $results[2]->name);

        $sql = <<<SQL
SELECT employee.name
FROM employee
WHERE (employee.deleted = ?) AND (employee.id IN
    ((SELECT sub_chair_employee.id AS sub_chair_employee_id
    FROM chair sub_chair
    INNER JOIN employee sub_chair_employee
        ON (sub_chair_employee.id = sub_chair.employee_id) AND (sub_chair_employee.deleted = ?)
    WHERE (sub_chair.vendor IS NOT NULL) AND (sub_chair.department_id = ?))))
ORDER BY employee.id
SQL;

        $this->assertSql(
            $sql,
            $employees->assembleSelect(),
            ['n', 'n', 1]
        );
    }

    #[DataProvider('databases')]
    public function testUnlikeWithASingleWildcardIsAppliedToOtherNegativeComparisons(Connection $db): void
    {
        $this->insertEmployeesWithChairs($db);

        $employees = Employee::on($db)
            ->columns('employee.name')
            ->orderBy('employee.id')
            ->filter(Filter::all(
                Filter::unlike('chair.vendor', '*'),
                Filter::unequal('chair.department_id', 1)
            ));

        $results = iterator_to_array($employees);

        $this->assertCount(2, $results);
        $this->assertSame('Pluto', $results[0]->name);
        $this->assertSame('Donald', $results[1]->name);


        if ($db->getAdapter() instanceof Pgsql) {
            $sql = <<<SQL
SELECT employee.name
FROM employee
WHERE (employee.deleted = ?) AND (((employee.id NOT IN
    ((SELECT sub_chair_employee.id AS sub_chair_employee_id
    FROM chair sub_chair
    INNER JOIN employee sub_chair_employee
        ON (sub_chair_employee.id = sub_chair.employee_id) AND (sub_chair_employee.deleted = ?)
    WHERE (sub_chair.vendor IS NOT NULL) AND (sub_chair_employee.id IS NOT NULL)
    GROUP BY sub_chair_employee.id
    HAVING COUNT(DISTINCT (sub_chair.department_id, sub_chair.employee_id)) >= ?))
  OR employee.id IS NULL)) AND ((employee.id NOT IN
    ((SELECT sub_chair_employee.id AS sub_chair_employee_id
    FROM chair sub_chair
    INNER JOIN employee sub_chair_employee
        ON (sub_chair_employee.id = sub_chair.employee_id) AND (sub_chair_employee.deleted = ?)
    WHERE (sub_chair.department_id = ?) AND (sub_chair_employee.id IS NOT NULL)
    GROUP BY sub_chair_employee.id
    HAVING COUNT(DISTINCT (sub_chair.department_id, sub_chair.employee_id)) >= ?))
  OR employee.id IS NULL)))
ORDER BY employee.id
SQL;
        } else {
            $sql = <<<SQL
SELECT employee.name
FROM employee
WHERE (employee.deleted = ?) AND (((employee.id NOT IN
    ((SELECT sub_chair_employee.id AS sub_chair_employee_id
    FROM chair sub_chair
    INNER JOIN employee sub_chair_employee
        ON (sub_chair_employee.id = sub_chair.employee_id) AND (sub_chair_employee.deleted = ?)
    WHERE (sub_chair.vendor IS NOT NULL) AND (sub_chair_employee.id IS NOT NULL)
    GROUP BY sub_chair_employee.id
    HAVING COUNT(DISTINCT sub_chair.department_id, sub_chair.employee_id) >= ?))
  OR employee.id IS NULL)) AND ((employee.id NOT IN
    ((SELECT sub_chair_employee.id AS sub_chair_employee_id
    FROM chair sub_chair
    INNER JOIN employee sub_chair_employee
        ON (sub_chair_employee.id = sub_chair.employee_id) AND (sub_chair_employee.deleted = ?)
    WHERE (sub_chair.department_id = ?) AND (sub_chair_employee.id IS NOT NULL)
    GROUP BY sub_chair_employee.id
    HAVING COUNT(DISTINCT sub_chair.department_id, sub_chair.employee_id) >= ?))
  OR employee.id IS NULL)))
ORDER BY employee.id
SQL;
        }

        $this->assertSql(
            $sql,
            $employees->assembleSelect(),
            ['n', 'n', 1, 'n', 1, 1]
        );
    }

    protected function insertEmployeesWithChairs(Connection $db): void
    {
        foreach (['Minnie', 'Goofy', 'Pluto', 'Donald', 'Mickey', 'Scrooge'] as $i => $name) {
            $db->insert(
                'employee',
                ['id' => $i + 1, 'department_id' => 1, 'name' => $name, 'role' => 'Developer', 'deleted' => 'n']
            );
        }

        // Minnie has a chair without vendor in department 1 and one with vendor in department 2, Goofy one with vendor
        // in department 1, Pluto none, Donald one without vendor in department 2, Mickey ones with vendor in both and
        // Scrooge one with vendor and one without in the same department
        $db->insert('chair', ['department_id' => 1, 'employee_id' => 1, 'vendor' => null]);
        $db->insert('chair', ['department_id' => 2, 'employee_id' => 1, 'vendor' => 'Acme Chairs']);
        $db->insert('chair', ['department_id' => 1, 'employee_id' => 2, 'vendor' => 'Bcme Chairs']);
        $db->insert('chair', ['department_id' => 2, 'employee_id' => 4, 'vendor' => null]);
        $db->insert('chair', ['department_id' => 1, 'employee_id' => 5, 'vendor' => 'Ccme Chairs']);
        $db->insert('chair', ['department_id' => 2, 'employee_id' => 5, 'vendor' => 'Dcme Chairs']);
        $db->insert('chair', ['department_id' => 1, 'employee_id' => 6, 'vendor' => 'Ecme Chairs']);
        $db->insert('chair', ['department_id' => 1, 'employee_id' => 6, 'vendor' => null]);
    }

    protected function createSchema(Connection $db, string $driver): void
    {
        $db->exec('CREATE TABLE office (id INT PRIMARY KEY, city VARCHAR(255))');
        $db->exec('CREATE TABLE department (id INT PRIMARY KEY, name VARCHAR(255))');
        $db->exec(
            'CREATE TABLE employee (id INT PRIMARY KEY, department_id INT,'
            . ' office_id INT, name VARCHAR(255), role VARCHAR(255), active VARCHAR(1), deleted VARCHAR(1))'
        );
        $db->exec('CREATE TABLE chair (department_id INT, employee_id INT, vendor VARCHAR(255))');
    }

    protected function dropSchema(Connection $db, string $driver): void
    {
        $db->exec('DROP TABLE IF EXISTS chair');
        $db->exec('DROP TABLE IF EXISTS employee');
        $db->exec('DROP TABLE IF EXISTS department');
        $db->exec('DROP TABLE IF EXISTS office');
    }
}
