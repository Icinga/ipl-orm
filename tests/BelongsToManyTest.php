<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Relations;

class BelongsToManyTest extends \PHPUnit\Framework\TestCase
{
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
}
