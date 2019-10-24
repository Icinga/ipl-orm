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

    public function testLimit()
    {
        $limit = 25;
        $query = (new Query())
            ->limit($limit);

        $this->assertSame($limit, $query->getLimit());
    }

    public function testOffset()
    {
        $offset = 25;
        $query = (new Query())
            ->offset($offset);

        $this->assertSame($offset, $query->getOffset());
    }

    public function testEnsureRelationsCreatedCallsModelsCreateRelations()
    {
        $model = new TestModelWithCreateRelations();
        (new Query())
            ->setModel($model)
            ->ensureRelationsCreated();

        $this->assertSame(1, $model->relationsCreatedCount);
    }

    public function testMultipleCallsToEnsureRelationsCreatedCallsModelsCreateRelationsOnlyOnce()
    {
        $model = new TestModelWithCreateRelations();
        (new Query())
            ->setModel($model)
            ->ensureRelationsCreated()
            ->ensureRelationsCreated()
            ->ensureRelationsCreated();

        $this->assertSame(1, $model->relationsCreatedCount);
    }

    public function testGetWithReturnsEmptyArrayIfThereAreNoRelationsToEagerLoad()
    {
        $with = (new Query())->getWith();

        $this->assertIsArray($with);
        $this->assertEmpty($with);
    }

    public function testWithWithStringAsParamaterAddsTheCorrectRelationToEagerLoad()
    {
        $query = (new Query())
            ->setModel(new User());

        $query->with('profile');

        $this->assertSame($query->getRelations()->get('profile'), $query->getWith()['profile']);
    }

    public function testWithWithArrayAsParamaterAddsTheCorrectRelationsToEagerLoad()
    {
        $query = (new Query())
            ->setModel(new User());

        $query->with(['profile', 'group']);

        $this->assertSame($query->getRelations()->get('profile'), $query->getWith()['profile']);
        $this->assertSame($query->getRelations()->get('group'), $query->getWith()['group']);
    }

    /** @expectedException \InvalidArgumentException */
    public function testWithThrowsInvalidArgumentExceptionIfRelationDoesNotExist()
    {
        $query = (new Query())
            ->setModel(new User())
            ->with('invalid');
    }
}
