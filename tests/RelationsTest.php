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
        $relation = $relations->create(TestRelation::class, $name, $targetClass);

        $this->assertSame($name, $relation->getName());
        $this->assertSame($targetClass, $relation->getTargetClass());
    }

    /** @expectedException \InvalidArgumentException */
    public function testCreateThrowsInvalidArgumentExceptionIfClassIsNotASubclassOfRelation()
    {
        (new Relations())->create(TestModel::class, 'test', TestModel::class);
    }

    public function testHasReturnsTrueIfRelationExists()
    {
        $relations = new Relations();
        $relations->add(
            $relations->create(TestRelation::class, 'test', TestModel::class)
        );

        $this->assertTrue($relations->has('test'));
    }

    public function testHasReturnsFalseIfRelationDoesNotExist()
    {
        $relations = new Relations();

        $this->assertFalse($relations->has('test'));
    }

    /** @expectedException \InvalidArgumentException */
    public function testAddThrowsInvalidArgumentExceptionIfRelationWithTheSameNameAlreadyExists()
    {
        $relations = new Relations();
        $relation = $relations->create(TestRelation::class, 'test', TestModel::class);
        $relations->add($relation);
        $relations->add($relation);
    }

    public function testGetReturnsCorrectRelationIfRelationExists()
    {
        $relations = new Relations();
        $relation = $relations->create(TestRelation::class, 'test', TestModel::class);
        $relations->add($relation);

        $this->assertSame($relation, $relations->get('test'));
    }

    /** @expectedException \InvalidArgumentException */
    public function testGetThrowsInvalidArgumentExceptionIfRelationDoesNotExist()
    {
        (new Relations())->get('test');
    }
}
