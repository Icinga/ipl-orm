<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Model;
use ipl\Orm\Relations;

class Car extends Model
{
    public function getTableName()
    {
        return 'car';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'model_name',
            'manufacturer',
            'model_name_lowered' => 'lower(model_name)'
        ];
    }

    public function createRelations(Relations $relations)
    {
        $relations->hasMany('passenger', Passenger::class);

        $relations->belongsToMany('user', User::class)
            ->through(CarUser::class);

        $relations->belongsToMany('user_custom_keys', User::class)
            ->through(CarUserWithCustomKeys::class);
    }
}
