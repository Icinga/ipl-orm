<?php

namespace ipl\Tests\Orm\Lib\Model;

use ipl\Orm\Model;
use ipl\Orm\Relations;

class Office extends Model
{
    public function getTableName()
    {
        return 'office';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'city'
        ];
    }

    public function createRelations(Relations $relations)
    {
        $relations->hasMany('employee', Employee::class);
    }
}
