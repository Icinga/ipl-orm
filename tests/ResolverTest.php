<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Query;
use ipl\Orm\Resolver;
use ipl\Sql\Expression;
use ipl\Sql\QueryBuilder;
use PHPUnit\Framework\TestCase;

class ResolverTest extends TestCase
{
    public function testGetRelationsCallsModelsCreateRelations()
    {
        $model = new TestModelWithCreateRelations();
        (new Query())
            ->getResolver()
            ->getRelations($model);

        $this->assertSame(1, $model->relationsCreatedCount);
    }

    public function testMultipleCallsToGetRelationsCallsModelsCreateRelationsOnlyOnce()
    {
        $model = new TestModelWithCreateRelations();
        $query = new Query();
        $query->getResolver()->getRelations($model);
        $query->getResolver()->getRelations($model);
        $query->getResolver()->getRelations($model);

        $this->assertSame(1, $model->relationsCreatedCount);
    }

    public function testGetSelectColumnsReturnsEmptyArrayIfPrimaryKeyAndColumnsAreEmpty()
    {
        $model = new TestModel();
        $resolver = (new Query())->getResolver();
        $columns = $resolver->getSelectColumns($model);

        $this->assertTrue(is_array($columns));
        $this->assertEmpty($columns);
    }

    public function testGetSelectColumnsOnlyReturnsThePrimaryKeyAsArrayIfThereIsOnlyThePrimaryKeyAndItIsAString()
    {
        $model = new TestModelWithPrimaryKey();
        $resolver = (new Query())->getResolver();

        $this->assertSame((array) $model->getKeyName(), $resolver->getSelectColumns($model));
    }

    public function testGetSelectColumnsOnlyReturnsTheCompoundPrimaryKeyAsArrayIfTheresOnlyThePrimaryKeyAndItsCompound()
    {
        $model = new TestModelWithCompoundPrimaryKey();
        $resolver = (new Query())->getResolver();

        $this->assertSame($model->getKeyName(), $resolver->getSelectColumns($model));
    }

    public function testGetSelectColumnsOnlyReturnsTheColumnsIfThereIsNoPrimaryKey()
    {
        $model = new TestModelWithColumns();
        $resolver = (new Query())->getResolver();

        $this->assertSame($model->getColumns(), $resolver->getSelectColumns($model));
    }

    public function testGetSelectColumnsReturnsPrimaryKeyPlusColumnsInThatOrder()
    {
        $model = new TestModelWithPrimaryKeyAndColumns();
        $resolver = (new Query())->getResolver();

        $this->assertSame(
            array_merge((array) $model->getKeyName(), $model->getColumns()),
            $resolver->getSelectColumns($model)
        );
    }

    public function testGetSelectColumnsReturnsCompoundPrimaryKeyPlusColumnsInThatOrder()
    {
        $model = new TestModelWithCompoundPrimaryKeyAndColumns();
        $resolver = (new Query())->getResolver();

        $this->assertSame(array_merge($model->getKeyName(), $model->getColumns()), $resolver->getSelectColumns($model));
    }

    public function testQualifyColumnsReturnsTheColumnsAndAliasesPrefixedWithTheGivenTableName()
    {
        $model = new Profile();
        $columns = [
            'user_id',
            'given_name',
            'surname'
        ];
        $qualified = [
            'profile.user_id',
            'profile.given_name',
            'profile.surname'
        ];
        $query = (new Query())
            ->setModel($model)
            ->with('user');

        $this->assertSame($qualified, $query->getResolver()->qualifyColumns($columns, $model));
        $this->assertSame($qualified, $query->getResolver()->qualifyColumnsAndAliases($columns, $model, false));

        $model = $query->getWith()['profile.user']->getTarget();
        $columns = [
            'username',
            'password'
        ];
        $qualified = [
            'profile_user_username' => 'profile_user.username',
            'profile_user_password' => 'profile_user.password'
        ];

        $this->assertSame($qualified, $query->getResolver()->qualifyColumnsAndAliases($columns, $model));
    }

    public function testExpressionsCanBeResolvedAndQualified()
    {
        $model = new Car();
        $columns = [
            'expr1' => new Expression('COLLATE(%s, %s)', ['model_name', 'manufacturer']),
            'expr2' => new Expression('SUM(CASE WHEN %s IS NULL THEN 0 ELSE 1 END)', ['passenger.name'])
        ];
        $query = (new Query())
            ->setModel($model)
            ->columns($columns);

        $this->assertSame(
            'SELECT (COLLATE(car.model_name, car.manufacturer)) AS expr1'
            . ', (SUM(CASE WHEN car_passenger.name IS NULL THEN 0 ELSE 1 END)) AS expr2'
            . ' FROM car INNER JOIN passenger car_passenger ON car_passenger.car_id = car.id',
            (new QueryBuilder(new TestAdapter()))->assembleSelect($query->assembleSelect())[0]
        );
    }

    public function testDotSeparatedAliasesAreQualified()
    {
        $columns = [
            'u.username' => 'username',
            'u.password' => 'password'
        ];
        $qualified = [
            'u_username' => 'profile_user.username',
            'u_password' => 'profile_user.password'
        ];
        $query = (new Query())
            ->setModel(new Profile())
            ->with('user');

        $model = $query->getWith()['profile.user']->getTarget();
        $this->assertSame($qualified, $query->getResolver()->qualifyColumnsAndAliases($columns, $model));
    }

    public function testColumnsAreQualifiedByTableAlias()
    {
        $columns = [
            'test_user.username' => 'username',
            'test_user.password' => 'password'
        ];
        $qualified = [
            'test_user_username' => 'test_user_profile_test_user.username',
            'test_user_password' => 'test_user_profile_test_user.password'
        ];
        $query = (new Query())
            ->setModel(new TestUserProfile())
            ->with('test_user');

        $model = $query->getWith()['test_user_profile.test_user']->getTarget();
        $this->assertSame($qualified, $query->getResolver()->qualifyColumnsAndAliases($columns, $model));
    }
}
