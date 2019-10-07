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
            'SELECT id FROM test',
            $query->assembleSelect()
        );
    }

    public function testSelectFromModelWithJustColumns()
    {
        $model = new TestModelWithColumns();
        $query = (new Query())->setModel($model);

        $this->assertSql(
            'SELECT lorem, ipsum FROM test',
            $query->assembleSelect()
        );
    }

    public function testSelectFromModelWithCompoundPrimaryKey()
    {
        $model = new TestModelWithCompoundPrimaryKey();
        $query = (new Query())->setModel($model);

        $this->assertSql(
            'SELECT i, d FROM test',
            $query->assembleSelect()
        );
    }

    public function testSelectFromModelWithPrimaryKeyAndColumns()
    {
        $model = new TestModelWithPrimaryKeyAndColumns();
        $query = (new Query())->setModel($model);

        $this->assertSql(
            'SELECT id, lorem, ipsum FROM test',
            $query->assembleSelect()
        );
    }

    public function testSelectFromModelWithCompoundPrimaryKeyAndColumns()
    {
        $model = new TestModelWithCompoundPrimaryKeyAndColumns();
        $query = (new Query())->setModel($model);

        $this->assertSql(
            'SELECT i, d, lorem, ipsum FROM test',
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
            'SELECT lorem FROM test',
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
            'SELECT * FROM test LIMIT 25',
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
            'SELECT * FROM test OFFSET 25',
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
            'SELECT * FROM test LIMIT 25 OFFSET 25',
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
    user.id AS user_id, user.username AS user_username, user.password AS user_password,
    profile.id AS profile_id, profile.user_id AS profile_user_id, profile.given_name AS profile_given_name,
    profile.surname AS profile_surname
FROM
    user
INNER JOIN
    profile profile ON profile.user_id = user.id
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
            'SELECT * FROM user INNER JOIN profile profile ON profile.user_id = user.id',
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
    profile.id AS profile_id, profile.user_id AS profile_user_id, profile.given_name AS profile_given_name,
    profile.surname AS profile_surname,
    user.id AS user_id, user.username AS user_username, user.password AS user_password
FROM
    profile
INNER JOIN
    user user ON user.id = profile.user_id
SQL;

        $this->assertSql(
            $sql,
            $query->assembleSelect()
        );
    }

    public function testSelectFromModelWithEagerLoadingOfASignleManyToManyRelation()
    {
        $user = new User();
        $query = (new Query())
            ->setModel($user)
            ->with('group');

        $sql = <<<'SQL'
SELECT
    user.id AS user_id, user.username AS user_username, user.password AS user_password,
    group.id AS group_id, group.name AS group_name
FROM
    user
INNER JOIN
    user_group user_group ON user_group.user_id = user.id
INNER JOIN
    group group ON group.id = user_group.group_id
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
            ->columns(['user.username', 'profile.given_name', 'profile.surname']);

        $sql = <<<'SQL'
SELECT
    user.username AS user_username,
    profile.given_name AS profile_given_name, profile.surname AS profile_surname
FROM
    user
INNER JOIN
    profile profile ON profile.user_id = user.id
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
            ->with('user.audit');

        $sql = <<<'SQL'
SELECT
    group.id AS group_id, group.name AS group_name,
    user.id AS user_id, user.username AS user_username, user.password AS user_password,
    audit.id AS audit_id, audit.user_id AS audit_user_id, audit.activity AS audit_activity
FROM
    group
INNER JOIN
    user_group user_group ON user_group.group_id = group.id
INNER JOIN
    user user ON user.id = user_group.user_id
INNER JOIN
    audit audit ON audit.user_id = user.id
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
