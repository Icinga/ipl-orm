<?php

namespace ipl\Tests\Orm\Lib\Model;

use ipl\Orm\Model;
use ipl\Orm\Relations;

class Employee extends Model
{
    public function getTableName()
    {
        return 'employee';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'name',
            'role',
            'department_id',
            'office_id'
        ];
    }

    public function createRelations(Relations $relations)
    {
        $relations->belongsTo('department', Department::class);
        $relations->belongsTo('office', Office::class)
            ->setJoinType('LEFT');
    }
}
