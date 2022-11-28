<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Exception\InvalidRelationException;
use ipl\Orm\Query;
use ipl\Sql\Expression;
use ipl\Tests\Sql\TestCase;

class QueryTest extends TestCase
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

    public function testWithQualifiesRelationNamesWithTableAlias()
    {
        $query = (new Query())
            ->setModel(new TestUserProfile());

        $query->with(['test_user']);

        $with = $query->getWith();

        $this->assertTrue(isset($with['test_user_profile.test_user']));
    }

    public function testAliasedModelColumnsCanBeSelected()
    {
        $query = (new Query())
            ->setModel(new TestModelWithAliasedColumns())
            ->columns([
                'dolor',
                'sit'
            ]);

        $this->assertSame(
            [
                'dolor' => 'test.sit',
                'test.sit'
            ],
            $query->assembleSelect()->getColumns()
        );
    }

    public function testModelAliasesAreQualifiedButCustomAliasesAreNot()
    {
        $query = (new Query())
            ->setModel(new Car())
            ->columns([
                'gender' => 'passenger.gender',
                'passenger.gender'
            ]);

        $this->assertSame(
            [
                'gender' => 'car_passenger.sex',
                'car_passenger_gender' => 'car_passenger.sex'
            ],
            $query->assembleSelect()->getColumns()
        );
    }

    public function testModelAliasesDoNotCollideWithCustomAliases()
    {
        $query = (new Query())
            ->setModel(new Car())
            ->columns([
                'gender'             => 'manufacturer', // Collided previously with car_passenger_gender
                'model_name_lowered' => 'model_name', // Only persists if custom aliases have preference
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

    public function testQueryWithMultipleSortDirectionsInOrderBy()
    {
        $query = (new Query())
            ->setModel(new User())
            ->withColumns(['api_identity.api_token'])
            ->orderBy('username', 'DESC')
            ->orderBy('password', 'ASC')
            ->orderBy('api_identity.api_token', 'DESC');

        $orderBy = $query->assembleSelect()->getOrderBy();

        $this->assertSame(
            [['user.username', 'DESC'], ['user.password', 'ASC'], ['user_api_identity_api_token', 'DESC']],
            $orderBy
        );
    }

    public function testQueryWithExpressionInOrderByThatUsesColumns()
    {
        $expression = new Expression("%s || ' ' || %s", ['username', 'profile.given_name']);

        $model = new User();
        $query = (new Query())
            ->setModel($model)
            ->with(['profile'])
            ->columns($model->getColumns())
            ->orderBy($expression);

        $sql = <<<SQL
SELECT user.username, user.password
FROM user
INNER JOIN profile user_profile ON user_profile.user_id = user.id
ORDER BY user.username || ' ' || user_profile.given_name
SQL;

        $this->assertSql(
            $sql,
            $query->assembleSelect(),
            null,
            'Base table and relation columns are incorrectly qualified in ORDER BY'
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

    public function testHydrateResultsByTableAliases()
    {
        $columns = [
            'given_name'                           => 'Baboo',
            'surname'                              => 'Mccarthy',
            'test_user_profile_test_user_username' => 'John Doe',
            'test_user_profile_test_user_password' => 'secret',
        ];
        $query = (new Query())
            ->setModel(new TestUserProfile())
            ->with('test_user');

        $model = $query->getModel();
        $hydrator = $query->createHydrator();
        $profile = $hydrator->hydrate($columns, new $model());

        $this->assertSame('Baboo', $profile->given_name);
        $this->assertSame('Mccarthy', $profile->surname);

        $this->assertSame('John Doe', $profile->test_user->username);
        $this->assertSame('secret', $profile->test_user->password);
    }

    public function testWithColumnsDoesNotDuplicateBaseTableColumns()
    {
        $query = (new Query())
            ->setModel(new User())
            ->withColumns('user.username');

        $this->assertSame(
            [
                'user.id',
                'user.username',
                'user.password'
            ],
            $query->assembleSelect()->getColumns()
        );
    }
}
