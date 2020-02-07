<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Query;
use ipl\Sql\QueryBuilder;

class SqlTest extends \PHPUnit\Framework\TestCase
{
    /** @var \ipl\Sql\QueryBuilder */
    protected $queryBuilder;

    public function testSelectFromModelWithJustAPrimaryKey()
    {
        $model = new TestModelWithPrimaryKey();
        $query = (new Query())->setModel($model);

        $this->assertSql(
            'SELECT test.id FROM test',
            $query->assembleSelect()
        );
    }

    public function testSelectFromModelWithJustColumns()
    {
        $model = new TestModelWithColumns();
        $query = (new Query())->setModel($model);

        $this->assertSql(
            'SELECT test.lorem, test.ipsum FROM test',
            $query->assembleSelect()
        );
    }

    public function testSelectFromModelWithCompoundPrimaryKey()
    {
        $model = new TestModelWithCompoundPrimaryKey();
        $query = (new Query())->setModel($model);

        $this->assertSql(
            'SELECT test.i, test.d FROM test',
            $query->assembleSelect()
        );
    }

    public function testSelectFromModelWithPrimaryKeyAndColumns()
    {
        $model = new TestModelWithPrimaryKeyAndColumns();
        $query = (new Query())->setModel($model);

        $this->assertSql(
            'SELECT test.id, test.lorem, test.ipsum FROM test',
            $query->assembleSelect()
        );
    }

    public function testSelectFromModelWithCompoundPrimaryKeyAndColumns()
    {
        $model = new TestModelWithCompoundPrimaryKeyAndColumns();
        $query = (new Query())->setModel($model);

        $this->assertSql(
            'SELECT test.i, test.d, test.lorem, test.ipsum FROM test',
            $query->assembleSelect()
        );
    }

    public function testSelectFromModelWithExplicitColumns()
    {
        $model = new TestModelWithColumns();
        $query = (new Query())
            ->setModel($model)
            ->columns(['lorem']);

        $this->assertSql(
            'SELECT test.lorem FROM test',
            $query->assembleSelect()
        );
    }

    public function testSelectFromModelWithExplicitAliasedColumns()
    {
        $model = new TestModelWithColumns();
        $query = (new Query())
            ->setModel($model)
            ->columns(['test_lorem' => 'lorem']);

        $this->assertSql(
            'SELECT test.lorem AS test_lorem FROM test',
            $query->assembleSelect()
        );
    }

    public function testSelectFromModelWithLimit()
    {
        $model = new TestModel();
        $query = (new Query())
            ->setModel($model)
            ->columns('*')
            ->limit(25);

        $this->assertSql(
            'SELECT test.* FROM test LIMIT 25',
            $query->assembleSelect()
        );
    }

    public function testSelectFromModelWithOffset()
    {
        $model = new TestModel();
        $query = (new Query())
            ->setModel($model)
            ->columns('*')
            ->offset(25);

        $this->assertSql(
            'SELECT test.* FROM test OFFSET 25',
            $query->assembleSelect()
        );
    }

    public function testSelectFromModelWithLimitAndOffset()
    {
        $model = new TestModel();
        $query = (new Query())
            ->setModel($model)
            ->columns('*')
            ->limit(25)
            ->offset(25);

        $this->assertSql(
            'SELECT test.* FROM test LIMIT 25 OFFSET 25',
            $query->assembleSelect()
        );
    }

    public function testSelectFromModelWithEagerLoadingOfASingleOneToOneRelation()
    {
        $user = new User();
        $query = (new Query())
            ->setModel($user)
            ->with('profile');

        $sql = <<<'SQL'
SELECT
    user.id, user.username, user.password,
    user_profile.id AS user_profile_id, user_profile.user_id AS user_profile_user_id,
    user_profile.given_name AS user_profile_given_name, user_profile.surname AS user_profile_surname
FROM
    user
INNER JOIN
    profile user_profile ON user_profile.user_id = user.id
SQL;

        $this->assertSql(
            $sql,
            $query->assembleSelect()
        );
    }

    public function testSelectFromModelWithEagerLoadingOfASingleOneToOneRelationAndExplicitColumnsToSelect()
    {
        $user = new User();
        $query = (new Query())
            ->setModel($user)
            ->columns('*')
            ->with('profile');

        $this->assertSql(
            'SELECT user.* FROM user INNER JOIN profile user_profile ON user_profile.user_id = user.id',
            $query->assembleSelect()
        );
    }

    public function testSelectFromModelWithEagerLoadingOfASingleOneToOneRelationInversed()
    {
        $profile = new Profile();
        $query = (new Query())
            ->setModel($profile)
            ->with('user');

        $sql = <<<'SQL'
SELECT
    profile.id, profile.user_id, profile.given_name, profile.surname,
    profile_user.id AS profile_user_id, profile_user.username AS profile_user_username,
    profile_user.password AS profile_user_password
FROM
    profile
INNER JOIN
    user profile_user ON profile_user.id = profile.user_id
SQL;

        $this->assertSql(
            $sql,
            $query->assembleSelect()
        );
    }

    public function testSelectFromModelWithEagerLoadingOfASingleManyToManyRelation()
    {
        $user = new User();
        $query = (new Query())
            ->setModel($user)
            ->with('group');

        $sql = <<<'SQL'
SELECT
    user.id, user.username, user.password,
    user_group.id AS user_group_id, user_group.name AS user_group_name
FROM
    user
INNER JOIN
    user_group user_user_group ON user_user_group.user_id = user.id
INNER JOIN
    group user_group ON user_group.id = user_user_group.group_id
SQL;

        $this->assertSql(
            $sql,
            $query->assembleSelect()
        );
    }

    public function testSelectFromModelWithExplicitColumnsToSelectAutomaticallyEagerLoadsTheCorrespondingRelation()
    {
        $user = new User();
        $query = (new Query())
            ->setModel($user)
            ->columns(['user_username' => 'user.username', 'profile.given_name', 'profile.surname']);

        $sql = <<<'SQL'
SELECT
    user.username AS user_username,
    user_profile.given_name AS user_profile_given_name, user_profile.surname AS user_profile_surname
FROM
    user
INNER JOIN
    profile user_profile ON user_profile.user_id = user.id
SQL;

        $this->assertSql(
            $sql,
            $query->assembleSelect()
        );
    }

    public function testSelectFromModelWithNestedWith()
    {
        $group = new Group();
        $query = (new Query())
            ->setModel($group)
            ->with(['user', 'user.audit']);

        $sql = <<<'SQL'
SELECT
    group.id,
    group.name,
    group_user.id AS group_user_id,
    group_user.username AS group_user_username,
    group_user.password AS group_user_password,
    group_user_audit.id AS group_user_audit_id,
    group_user_audit.user_id AS group_user_audit_user_id,
    group_user_audit.activity AS group_user_audit_activity
FROM
    group
INNER JOIN
    user_group group_user_group ON group_user_group.group_id = group.id
INNER JOIN
    user group_user ON group_user.id = group_user_group.user_id
INNER JOIN
    audit group_user_audit ON group_user_audit.user_id = group_user.id
SQL;

        $this->assertSql(
            $sql,
            $query->assembleSelect()
        );
    }

    public function setUp()
    {
        $this->queryBuilder = new QueryBuilder(new TestAdapter());
    }

    public function assertSql($sql, $query, $values = null)
    {
        // Reduce whitespaces to just one space
        $sql = preg_replace('/\s+/', ' ', trim($sql));

        list($stmt, $bind) = $this->queryBuilder->assemble($query);

        $this->assertSame($sql, $stmt);

        if ($values !== null) {
            $this->assertSame($values, $bind);
        }
    }
}
