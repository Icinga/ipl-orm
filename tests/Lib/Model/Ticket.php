<?php

namespace ipl\Tests\Orm\Lib\Model;

use ipl\Orm\Model;
use ipl\Orm\Relations;

class Ticket extends Model
{
    public function getTableName()
    {
        return 'ticket';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'subject',
            'open',
            'employee_id'
        ];
    }

    public function createRelations(Relations $relations)
    {
        $relations->belongsTo('employee', Employee::class);
    }
}
