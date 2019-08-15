<?php

namespace ipl\Tests\Orm;

class TestModelWithPrimaryKeyAndColumns extends TestModelWithPrimaryKey
{
    public function getColumns()
    {
        return ['lorem', 'ipsum'];
    }
}
