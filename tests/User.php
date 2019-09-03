<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Model;
use ipl\Orm\Relations;

class User extends Model
{
    public function getTableName()
    {
        return 'user';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'username',
            'password',
        ];
    }

    public function createRelations(Relations $relations)
    {
        $relations->add(
            $relations->create('profile', Profile::class)
        );
    }
}
