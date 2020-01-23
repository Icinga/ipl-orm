<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Query;
use ipl\Orm\Resolver;
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
        $resolver = new Resolver();
        $columns = $resolver->getSelectColumns($model);

        $this->assertInternalType('array', $columns);
        $this->assertEmpty($columns);
    }

    public function testGetSelectColumnsOnlyReturnsThePrimaryKeyAsArrayIfThereIsOnlyThePrimaryKeyAndItIsAString()
    {
        $model = new TestModelWithPrimaryKey();
        $resolver = new Resolver();

        $this->assertSame((array) $model->getKeyName(), $resolver->getSelectColumns($model));
    }

    public function testGetSelectColumnsOnlyReturnsTheCompoundPrimaryKeyAsArrayIfTheresOnlyThePrimaryKeyAndItsCompound()
    {
        $model = new TestModelWithCompoundPrimaryKey();
        $resolver = new Resolver();

        $this->assertSame($model->getKeyName(), $resolver->getSelectColumns($model));
    }

    public function testGetSelectColumnsOnlyReturnsTheColumnsIfThereIsNoPrimaryKey()
    {
        $model = new TestModelWithColumns();
        $resolver = new Resolver();

        $this->assertSame($model->getColumns(), $resolver->getSelectColumns($model));
    }

    public function testGetSelectColumnsReturnsPrimaryKeyPlusColumnsInThatOrder()
    {
        $model = new TestModelWithPrimaryKeyAndColumns();
        $resolver = new Resolver();

        $this->assertSame(
            array_merge((array) $model->getKeyName(), $model->getColumns()),
            $resolver->getSelectColumns($model)
        );
    }

    public function testGetSelectColumnsReturnsCompoundPrimaryKeyPlusColumnsInThatOrder()
    {
        $model = new TestModelWithCompoundPrimaryKeyAndColumns();
        $resolver = new Resolver();

        $this->assertSame(array_merge($model->getKeyName(), $model->getColumns()), $resolver->getSelectColumns($model));
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
        $resolver = new Resolver();

        $this->assertSame($qualified, $resolver->qualifyColumnsAndAliases($columns, $tableName));
    }
}
