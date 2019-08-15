<?php

namespace ipl\Tests\Orm;

class TestModelWithCompoundPrimaryKey extends TestModel
{
    public function getKeyName()
    {
        return ['i', 'd'];
    }
}
