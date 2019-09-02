<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Model;

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
}
