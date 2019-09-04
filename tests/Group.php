<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Model;
use ipl\Orm\Relations;

class Group extends Model
{
    public function getTableName()
    {
        return 'group';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'name'
        ];
    }

    public function createRelations(Relations $relations)
    {
        $relations
            ->belongsToMany('user', User::class)
            ->setThrough('user_group');
    }
}
