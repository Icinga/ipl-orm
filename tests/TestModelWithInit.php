<?php

namespace ipl\Tests\Orm;

class TestModelWithInit extends TestModel
{
    public $propertyInitialized = false;

    protected function init()
    {
        $this->propertyInitialized = true;
    }
}
