<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Model;
use ipl\Orm\Relations;

class Profile extends Model
{
    public function getTableName()
    {
        return 'profile';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'user_id',
            'given_name',
            'surname'
        ];
    }

    public function createRelations(Relations $relations)
    {
        $relations->belongsTo('user', User::class);
    }
}
