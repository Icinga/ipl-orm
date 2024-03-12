<?php

namespace ipl\Tests\Orm\Lib\Model;

use ipl\Orm\Model;
use ipl\Orm\Relations;

class Department extends Model
{
    public function getTableName()
    {
        return 'department';
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
        $relations->hasMany('employee', Employee::class)
            ->setJoinType('LEFT');
    }
}
