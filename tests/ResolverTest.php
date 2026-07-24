<?php

namespace ipl\Tests\Orm;

use InvalidArgumentException;
use ipl\Orm\Query;
use ipl\Orm\Relation\Junction;
use ipl\Sql\Expression;
use ipl\Sql\QueryBuilder;
use ipl\Stdlib\Filter;
use ipl\Tests\Orm\Lib\Model\Department;
use ipl\Tests\Orm\Lib\Model\Employee;
use ipl\Tests\Orm\Lib\Model\RestrictedGroup;
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

    public function testExpressionsWithRelationReferencesCanBeResolvedAndQualified()
    {
        $model = new TestModelWithExpressions();
        $query = (new Query())
            ->setModel($model)
            ->columns(['expr1', 'expr2', 'more.expr3']);

        $this->assertSame(
            'SELECT (LOWER(test.uppercase_text)) AS expr1,'
                . ' (test_relation.lorem + test_relation.ipsum) AS expr2,'
                . ' (test_more_related.lorem + test_more_related.ipsum) AS test_more_expr3'
                . ' FROM test'
                . ' INNER JOIN test test_more ON test_more.id = test.test_id'
                . ' INNER JOIN test test_relation ON test_relation.test_id = test.id'
                . ' INNER JOIN test test_more_related ON test_more_related.test_id = test_more.id',
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

    public function testGetVisibilityFilterReturnsAnEmptyChainForModelsWithoutOne()
    {
        $query = (new Query())->setModel(new User());
        $filter = $query->getResolver()->getVisibilityFilter($query->getModel());

        $this->assertInstanceOf(Filter\Chain::class, $filter);
        $this->assertTrue($filter->isEmpty(), 'Visibility filter of a model without one is not empty');
    }

    public function testGetVisibilityFilterResolvesAndCachesTheModelsFilter()
    {
        $query = (new Query())->setModel(new RestrictedGroup());
        $resolver = $query->getResolver();

        $filter = $resolver->getVisibilityFilter($query->getModel());

        $rules = iterator_to_array($filter->yieldRules());
        $this->assertCount(1, $rules);
        $this->assertSame('restricted_group.deleted', $rules[0]->getColumn());
        $this->assertSame('n', $rules[0]->getValue());

        // Subsequent calls return the very same (cached) resolved instance
        $this->assertSame($filter, $resolver->getVisibilityFilter($query->getModel()));
    }

    public function testResolveRelationFilterQualifiesTargetColumnsByDefault()
    {
        $resolver = (new Query())->setModel(new Department())->getResolver();

        $filter = Filter::all(Filter::equal('active', 'y'), Filter::equal('employee.role', 'lead'));
        $resolver->resolveRelationFilter($filter, 'relation', new Department(), new Employee());

        $columns = array_map(fn ($rule) => $rule->getColumn(), iterator_to_array($filter->yieldRules()));
        $this->assertSame(['employee.active', 'employee.role'], $columns);
    }

    public function testResolveRelationFilterQualifiesSourceColumns()
    {
        $resolver = (new Query())->setModel(new Department())->getResolver();

        $filter = Filter::all(Filter::equal('department.name', 'Engineering'));
        $resolver->resolveRelationFilter($filter, 'relation', new Department(), new Employee());

        $this->assertSame('department.name', iterator_to_array($filter->yieldRules())[0]->getColumn());
    }

    public function testResolveRelationFilterQualifiesRelationColumns()
    {
        $resolver = (new Query())->setModel(new Department())->getResolver();

        $filter = Filter::all(Filter::equal('supplementary.name', 'Q/A'));
        $resolver->resolveRelationFilter($filter, 'supplementary', new Department(), new Department());

        $this->assertSame('supplementary.name', iterator_to_array($filter->yieldRules())[0]->getColumn());
    }

    public function testResolveRelationFilterThrowsForAnUnknownAlias()
    {
        $resolver = (new Query())->setModel(new Department())->getResolver();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid relation alias "office"');

        $resolver->resolveRelationFilter(
            Filter::all(Filter::equal('office.city', 'London')),
            'relation',
            new Department(),
            new Employee()
        );
    }

    public function testResolveRelationFilterThrowsForANonSelectableColumn()
    {
        $resolver = (new Query())->setModel(new Department())->getResolver();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('non-selectable column "unknown"');

        $resolver->resolveRelationFilter(
            Filter::all(Filter::equal('unknown', 'x')),
            'relation',
            new Department(),
            new Employee()
        );
    }

    public function testResolveRelationFilterDoesNotValidateJunctionColumns()
    {
        $resolver = (new Query())->setModel(new Department())->getResolver();
        $junction = (new Junction())->setTableName('membership');

        $filter = Filter::all(Filter::equal('membership.since', '2020'));
        $resolver->resolveRelationFilter($filter, 'relation', new Department(), $junction);

        $this->assertSame('membership.since', iterator_to_array($filter->yieldRules())[0]->getColumn());
    }

    public function testQualifyFilterThrowsForAnUnknownModelAlias()
    {
        $query = (new Query())->setModel(new Department());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown model alias "employee"');

        $query->getResolver()->qualifyFilter(
            Filter::all(Filter::equal('employee.active', 'y')),
            $query->getModel()
        );
    }

    public function testQualifyFilterDoesNotModifyTheGivenFilter()
    {
        $query = (new Query())->setModel(new Department());

        $original = Filter::all(Filter::equal('department.name', 'Engineering'));
        $qualified = $query->getResolver()->qualifyFilter($original, $query->getModel());

        // The chain is deep cloned, hence the original is left untouched
        $this->assertNotSame($original, $qualified, 'The given filter has not been cloned');
        $this->assertSame('department.name', iterator_to_array($original->yieldRules())[0]->getColumn());
    }
}
