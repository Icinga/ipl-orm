<?php

namespace ipl\Tests\Orm\Lib\Model;

use ipl\Orm\Model;
use ipl\Orm\Relations;
use ipl\Stdlib\Filter;
use ipl\Tests\Orm\Car;
use ipl\Tests\Orm\CarUser;
use ipl\Tests\Orm\Group;
use ipl\Tests\Orm\Lib\Model\RestrictedGroup;

class RestrictedUser extends Model
{
    public function getTableName()
    {
        return 'restricted_user';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'username'
        ];
    }

    public function createRelations(Relations $relations)
    {
        $relations->hasMany('restricted_group', RestrictedGroup::class);
        $relations->hasMany('vip_group', Group::class)
            ->setFilter(Filter::equal('name', 'vip'));
        $relations->belongsToMany('car', Car::class)
            ->through(CarUser::class)
            ->setThroughFilter(Filter::equal('user_id', 5))
            ->setFilter(Filter::equal('manufacturer', 'Icinga'));
        $relations->belongsToMany('shared_group', Group::class)
            ->setThroughAlias('sg')
            ->through('user_group')
            ->setThroughFilter(Filter::equal('active', 'y'));
    }
}
