<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Compat\FilterProcessor;
use ipl\Orm\Query;
use ipl\Sql\Connection;
use ipl\Sql\Test\Databases;
use ipl\Stdlib\Filter;
use ipl\Tests\Orm\Lib\Model\Office;

class FilterProcessorTest extends \PHPUnit\Framework\TestCase
{
    use Databases;

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

        $this->assertSame('London', $results[0]['city'] ?? 'not found');
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
}
