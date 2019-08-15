<?php

namespace ipl\Tests\Orm;

class TestModelWithColumns extends TestModel
{
    public function getColumns()
    {
        return ['lorem', 'ipsum'];
    }
}
