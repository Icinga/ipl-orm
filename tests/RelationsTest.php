<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Relations;

class RelationsTest extends \PHPUnit\Framework\TestCase
{
    public function testCreateReturnsRelationInstance()
    {
        $relations = new Relations();
        $name = 'test';
        $targetClass = TestModel::class;
        $relation = $relations->create($name, $targetClass);

        $this->assertSame($name, $relation->getName());
        $this->assertSame($targetClass, $relation->getTargetClass());
    }

    public function testHasReturnsTrueIfRelationExists()
    {
        $relations = new Relations();
        $relations->create('test', TestModel::class);

        $this->assertTrue($relations->has('test'));
    }

    public function testHasReturnsFalseIfRelationDoesNotExist()
    {
        $relations = new Relations();

        $this->assertFalse($relations->has('test'));
    }
}
