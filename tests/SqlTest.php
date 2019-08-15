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

    public function setUp()
    {
        $this->queryBuilder = new QueryBuilder(new TestAdapter());
    }

    public function assertSql($sql, $query, $values = null)
    {
        list($stmt, $bind) = $this->queryBuilder->assemble($query);

        $this->assertSame($sql, $stmt);

        if ($values !== null) {
            $this->assertSame($values, $bind);
        }
    }
}
