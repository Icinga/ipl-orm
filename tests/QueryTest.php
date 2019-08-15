<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Query;

class QueryTest extends \PHPUnit\Framework\TestCase
{
    public function testGetModelReturnsNullIfUnset()
    {
        $query = new Query();

        $this->assertNull($query->getModel());
    }

    public function testGetModelReturnsCorrectModelIfSet()
    {
        $model = new TestModel();
        $query = (new Query())
            ->setModel($model);

        $this->assertSame($model, $query->getModel());
    }

    /**
     * @expectedException \TypeError
     */
    public function testSetModelThrowsExceptionOnTypeMismatch()
    {
        (new Query())->setModel('invalid');
    }

    public function testGetDbReturnsNullIfUnset()
    {
        $query = new Query();

        $this->assertNull($query->getDb());
    }

    public function testGetDbReturnsCorrectDbIfSet()
    {
        $db = new TestConnection();
        $query = (new Query())
            ->setDb($db);

        $this->assertSame($db, $query->getDb());
    }

    /**
     * @expectedException \TypeError
     */
    public function testSetDbThrowsExceptionOnTypeMismatch()
    {
        (new Query())->setDb('invalid');
    }

    public function testGetColumnsReturnsEmptyArrayIfUnset()
    {
        $columns = (new Query())
            ->getColumns();

        $this->assertIsArray($columns);
        $this->assertEmpty($columns);
    }

    public function testGetColumnsReturnsCorrectColumnsIfSet()
    {
        $columns = ['lorem', 'ipsum'];
        $query = (new Query())
            ->columns($columns);

        $this->assertSame($columns, $query->getColumns());
    }

    public function testMultipleCallsToColumnsAreMerged()
    {
        $columns1 = ['lorem'];
        $columns2 = ['ipsum'];
        $query = (new Query())
            ->columns($columns1)
            ->columns($columns2);

        $this->assertSame(array_merge($columns1, $columns2), $query->getColumns());
    }

    public function testColumnsWithStringAsParameter()
    {
        $column = 'lorem';
        $query = (new Query())
            ->columns($column);

        $this->assertSame([$column], $query->getColumns());
    }
}
