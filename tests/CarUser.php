<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Model;
use ipl\Orm\Relations;

class CarUser extends Model
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
        $relations->hasMany('car', Car::class);

        $relations->hasMany('user', User::class);
    }
}
