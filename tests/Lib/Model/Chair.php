<?php

namespace ipl\Tests\Orm\Lib\Model;

use ipl\Orm\Model;
use ipl\Orm\Relations;

class Chair extends Model
{
    public function getTableName()
    {
        return 'chair';
    }

    public function getKeyName()
    {
        return ['department_id', 'employee_id'];
    }

    public function getColumns()
    {
        return [
            'department_id',
            'employee_id',
            'vendor'
        ];
    }

    public function createRelations(Relations $relations)
    {
        $relations->belongsTo('employee', Employee::class);
    }
}
