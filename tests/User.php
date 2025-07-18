<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Model;
use ipl\Orm\Relations;

class User extends Model
{
    public function getTableName()
    {
        return 'user';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'username',
            'password',
        ];
    }

    public function createRelations(Relations $relations)
    {
        $relations->hasOne('profile', Profile::class);
        $relations->hasOne('api_identity', ApiIdentity::class);

        $relations
            ->belongsToMany('group', Group::class)
            ->setThroughAlias('t_user_group')
            ->through('user_group');

        $relations->hasMany('audit', Audit::class);

        $relations->belongsToMany('car', Car::class)
            ->through(CarUser::class);

        $relations->belongsToMany('user_custom_keys', Car::class)
            ->through(CarUserWithCustomKeys::class);
    }
}
