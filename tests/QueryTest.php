<?php

namespace ipl\Tests\Orm;

use InvalidArgumentException;
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

    public function testGetColumnsReturnsEmptyArrayIfUnset()
    {
        $columns = (new Query())
            ->getColumns();

        $this->assertTrue(is_array($columns));
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

    public function testGetWithReturnsEmptyArrayIfThereAreNoRelationsToEagerLoad()
    {
        $with = (new Query())->getWith();

        $this->assertTrue(is_array($with));
        $this->assertEmpty($with);
    }

    public function testWithWithStringAsParamaterAddsTheCorrectRelationToEagerLoad()
    {
        $query = (new Query())
            ->setModel(new User());

        $query->with('profile');

        $this->assertSame(
            $query->getResolver()->getRelations($query->getModel())->get('profile'),
            $query->getWith()['user.profile']
        );
    }

    public function testWithWithArrayAsParamaterAddsTheCorrectRelationsToEagerLoad()
    {
        $query = (new Query())
            ->setModel(new User());

        $query->with(['profile', 'group']);

        $this->assertSame(
            $query->getResolver()->getRelations($query->getModel())->get('profile'),
            $query->getWith()['user.profile']
        );
        $this->assertSame(
            $query->getResolver()->getRelations($query->getModel())->get('group'),
            $query->getWith()['user.group']
        );
    }

    public function testWithThrowsInvalidArgumentExceptionIfRelationDoesNotExist()
    {
        $this->expectException(InvalidArgumentException::class);

        $query = (new Query())
            ->setModel(new User())
            ->with('invalid');
    }

    public function testModelAliasesAreQualifiedButCustomAliasesAreNot()
    {
        $query = (new Query())
            ->setModel(new Car())
            ->with('passenger')
            ->columns([
                'gender' => 'manufacturer', // Collided previously with car_passenger_gender
                'model_name_lowered' => 'model_name', // Only persists if custom aliases have preference
                '*'
            ]);

        $this->assertSame(
            [
                'gender' => 'car.manufacturer',
                'model_name_lowered' => 'car.model_name',
                'car.id',
                'car.model_name',
                'car_passenger_id' => 'car_passenger.id',
                'car_passenger_car_id' => 'car_passenger.car_id',
                'car_passenger_name' => 'car_passenger.name',
                'car_passenger_gender' => 'car_passenger.sex'
            ],
            $query->assembleSelect()->getColumns()
        );
    }
}
