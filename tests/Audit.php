<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Model;
use ipl\Orm\Relations;

class Audit extends Model
{
    public function getTableName()
    {
        return 'audit';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'user_id',
            'activity',
            'subsystem_id'
        ];
    }

    public function createRelations(Relations $relations)
    {
        $relations->hasOne('user', User::class);
        $relations->belongsToOne('subsystem', Subsystem::class);
    }
}
