<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Model;
use ipl\Orm\Relations;

class CarUserWithCustomKeys extends Model
{
    public function getTableName()
    {
        return 'car_user';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'car_id',
            'user_id'
        ];
    }

    public function createRelations(Relations $relations)
    {
        $relations->hasMany('car', Car::class)
            ->setCandidateKey('car_user_car_candidate_key')
            ->setForeignKey('car_custom_foreign_key');

        $relations->hasMany('user', User::class)
            ->setCandidateKey('car_user_user_candidate_key')
            ->setForeignKey('user_custom_foreign_key');
    }
}
