<?php

namespace ipl\Tests\Orm;

class TestModelWithPrimaryKey extends TestModel
{
    public function getKeyName()
    {
        return 'id';
    }
}
