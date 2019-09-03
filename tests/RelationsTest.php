<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Relation\BelongsTo;
use ipl\Orm\Relation\BelongsToMany;
use ipl\Orm\Relation\HasMany;
use ipl\Orm\Relation\HasOne;
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

    public function testHasOneReturnsOneToOneRelationship()
    {
        $relation = (new Relations())->hasOne('test', TestModel::class);

        /** @noinspection PhpParamsInspection */
        $this->assertInstanceOf(HasOne::class, $relation);
    }

    public function testHasManyReturnsOneToManyRelationship()
    {
        $relation = (new Relations())->hasMany('test', TestModel::class);

        /** @noinspection PhpParamsInspection */
        $this->assertInstanceOf(HasMany::class, $relation);
    }

    public function testBelongsToReturnsTheInverseRelationship()
    {
        $relation = (new Relations())->belongsTo('test', TestModel::class);

        /** @noinspection PhpParamsInspection */
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function testBelongsToManyReturnsManyToManyRelationship()
    {
        $relation = (new Relations())->belongsToMany('test', TestModel::class);

        /** @noinspection PhpParamsInspection */
        $this->assertInstanceOf(BelongsToMany::class, $relation);
    }
}
