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

    public function testQualifyColumnsReturnsTheColumnsAndAliasesPrefixedWithTheGivenTableName()
    {
        $tableName = 'profile';
        $columns = [
            'user_id',
            'given_name',
            'surname'
        ];
        $qualified = [
            'profile_user_id'    => 'profile.user_id',
            'profile_given_name' => 'profile.given_name',
            'profile_surname'    => 'profile.surname'
        ];

        $this->assertSame($qualified, Query::qualifyColumns($columns, $tableName));
    }
}
