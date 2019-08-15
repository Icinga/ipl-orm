<?php

namespace ipl\Tests\Orm;

class TestModelWithCompoundPrimaryKeyAndColumns extends TestModelWithCompoundPrimaryKey
{
    public function getColumns()
    {
        return ['lorem', 'ipsum'];
    }
}
