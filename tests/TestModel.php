<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Model;

class TestModel extends Model
{
    public function getTableName()
    {
        return null;
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
