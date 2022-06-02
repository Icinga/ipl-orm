<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Exception\InvalidRelationException;
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

    public function testExecuteReturnsCustomResultSet()
    {
        $query = (new Query())->setResultSetClass(TestResultSet::class);
        $this->assertInstanceOf(TestResultSet::class, $query->execute());
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

    public function testMultipleCallsToColumnsOverwriteEachOther()
    {
        $columns1 = ['lorem'];
        $columns2 = ['ipsum'];
        $query = (new Query())
            ->columns($columns1)
            ->columns($columns2);

        $this->assertSame($columns2, $query->getColumns());
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

    public function testWithThrowsInvalidRelationExceptionIfRelationDoesNotExist()
    {
        $this->expectException(InvalidRelationException::class);

        $query = (new Query())
            ->setModel(new User())
            ->with('invalid');
    }

    public function testModelAliasesAreQualifiedButCustomAliasesAreNot()
    {
        $query = (new Query())
            ->setModel(new Car())
            ->columns([
                'gender'             => 'manufacturer',
                'model_name_lowered' => 'model_name',
                'passenger.name',
                'passenger.gender'
            ]);

        $this->assertSame(
            [
                'gender'               => 'car.manufacturer',
                'model_name_lowered'   => 'car.model_name',
                'car_passenger_name'   => 'car_passenger.name',
                'car_passenger_gender' => 'car_passenger.sex'
            ],
            $query->assembleSelect()->getColumns()
        );
    }

    public function testAliasIsUsedForAliasedExpressionsInOrderBy()
    {
        $query = (new Query())
            ->setModel(new User())
            ->columns('api_identity.api_token')
            ->orderBy('api_identity.api_token', 'desc');

        $orderBy = $query->assembleSelect()->getOrderBy();

        $this->assertSame(
            [['user_api_identity_api_token', 'desc']],
            $orderBy
        );
    }

    public function testExplicitColumnsDontCauseRelationsToBeImplicitlySelected()
    {
        $query = (new Query())
            ->setModel(new User())
            ->with('profile')
            ->columns(['user.username', 'profile.surname']);

        $this->assertSame(
            [
                'user.username',
                'user_profile_surname' => 'user_profile.surname'
            ],
            $query->assembleSelect()->getColumns()
        );
    }

    public function testMultipleCallsToWithColumnsAreMerged()
    {
        $query = (new Query())
            ->setModel(new User())
            ->columns('id')
            ->withColumns('username')
            ->withColumns('password');

        $this->assertSame(
            [
                'user.id',
                'user.username',
                'user.password'
            ],
            $query->assembleSelect()->getColumns()
        );
    }

    public function testWithColumnsAdditivity()
    {
        $query = (new Query())
            ->setModel(new User())
            ->withcolumns('profile.surname');

        $this->assertSame(
            [
                'user.id',
                'user.username',
                'user.password',
                'user_profile_surname' => 'user_profile.surname',
            ],
            $query->assembleSelect()->getColumns()
        );
    }

    public function testWithColumnsDoesNotConstrainPreviouslyAddedRelation()
    {
        $query = (new Query())
            ->setModel(new User())
            ->with('profile')
            ->withcolumns('profile.surname');

        $this->assertSame(
            [
                'user.id',
                'user.username',
                'user.password',
                'user_profile_id'         => 'user_profile.id',
                'user_profile_user_id'    => 'user_profile.user_id',
                'user_profile_given_name' => 'user_profile.given_name',
                'user_profile_surname'    => 'user_profile.surname',
            ],
            $query->assembleSelect()->getColumns()
        );
    }
}
