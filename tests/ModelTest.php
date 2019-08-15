<?php

namespace ipl\Tests\Orm;

class ModelTest extends \PHPUnit\Framework\TestCase
{
    public function testInitIsCalledAfterConstruction()
    {
        $model = new TestModelWithInit();

        $this->assertTrue($model->propertyInitialized);
    }
}
