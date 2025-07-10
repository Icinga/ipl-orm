<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Query;
use ipl\Orm\Relations;
use ipl\Sql\Test\SqlAssertions;

class BelongsToManyTest extends \PHPUnit\Framework\TestCase
{
    use SqlAssertions;

    public function setUp(): void
    {
        $this->setUpSqlAssertions();
    }

    public function testResolveDefaultKeys()
    {
        $model = new Car();
        $relations = new Relations();
        $model->createRelations($relations);
        $expected = [
            [
                'from'          => 'car',
                'to'            => 'car_user',
                'candidate_key' => 'id',
                'foreign_key'   => 'car_id'
            ],
            [
                'from'          => 'car_user',
                'to'            => 'user',
                'candidate_key' => 'user_id',
                'foreign_key'   => 'id'
            ]
        ];
        $actual = [];
        foreach (
            $relations
                ->get('user')
                ->setSource($model)
                ->resolve() as list($from, $to, $keys)
        ) {
            reset($keys);
            $actual[] = [
                'from'          => $from->getTableName(),
                'to'            => $to->getTableName(),
                'candidate_key' => current($keys),
                'foreign_key'   => key($keys)
            ];
        }
        $this->assertSame($expected, $actual);
    }

    public function testResolveRespectsCustomKeysInTroughModels()
    {
        $model = new Car();
        $relations = new Relations();
        $model->createRelations($relations);
        $expected = [
            [
                'from'          => 'car',
                'to'            => 'car_user',
                'candidate_key' => 'car_custom_foreign_key',
                'foreign_key'   => 'car_user_car_candidate_key'
            ],
            [
                'from'          => 'car_user',
                'to'            => 'user',
                'candidate_key' => 'car_user_user_candidate_key',
                'foreign_key'   => 'user_custom_foreign_key'
            ]
        ];
        $actual = [];
        foreach (
            $relations
                ->get('user_custom_keys')
                ->setSource($model)
                ->resolve() as list($from, $to, $keys)
        ) {
            reset($keys);
            $actual[] = [
                'from'          => $from->getTableName(),
                'to'            => $to->getTableName(),
                'candidate_key' => current($keys),
                'foreign_key'   => key($keys)
            ];
        }
        $this->assertSame($expected, $actual);
    }

    public function testUniqueAliasesAreUsedToJoinThroughTables()
    {
        $profile = (new Query())
            ->setModel(new Group())
            ->with('group.user.group');

        $sql = <<<'SQL'
SELECT group.id,
       group.name,
       group_user_group.id AS group_user_group_id,
       group_user_group.name AS group_user_group_name
FROM group
    INNER JOIN user_group group_t_user_group ON group_t_user_group.group_id = group.id
    INNER JOIN user group_user ON group_user.id = group_t_user_group.user_id
    INNER JOIN user_group group_user_t_user_group ON group_user_t_user_group.user_id = group_user.id
    INNER JOIN group group_user_group ON group_user_group.id = group_user_t_user_group.group_id
SQL;

        $this->assertSql($sql, $profile->assembleSelect());
    }
}
