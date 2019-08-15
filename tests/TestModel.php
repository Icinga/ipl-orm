<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Model;

class TestModel extends Model
{
    public function getTableName()
    {
        return 'test';
    }

    public function getKeyName()
    {
        return null;
    }

    public function getColumns()
    {
        return null;
    }
}
