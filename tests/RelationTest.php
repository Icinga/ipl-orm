<?php

namespace ipl\Tests\Orm;

use InvalidArgumentException;
use ipl\Orm\Relation;

class RelationTest extends \PHPUnit\Framework\TestCase
{
    public function testGetNameReturnsNullIfUnset()
    {
        $this->assertNull((new Relation())->getName());
    }

    public function testGetNameReturnsCorrectNameIfSet()
    {
        $name = 'relation';
        $relation = (new Relation())
            ->setName($name);

        $this->assertSame($name, $relation->getName());
    }

    public function testGetForeignKeyReturnsNullIfUnset()
    {
        $this->assertNull((new Relation())->getForeignKey());
    }

    public function testGetForeignKeyReturnsCorrectArrayIfArrayHasBeenSet()
    {
        $foreignKey = ['foreign', 'key'];
        $relation = (new Relation())
            ->setForeignKey($foreignKey);

        $this->assertSame($foreignKey, $relation->getForeignKey());
    }

    public function testGetForeignKeyReturnsCorrectStringIfStringHasBeenSet()
    {
        $foreignKey = 'foreign_key';
        $relation = (new Relation())
            ->setForeignKey($foreignKey);

        $this->assertSame($foreignKey, $relation->getForeignKey());
    }

    public function testGetCandidateKeyReturnsNullIfUnset()
    {
        $this->assertNull((new Relation())->getCandidateKey());
    }

    public function testGetCandidateKeyReturnsCorrectArrayIfArrayHasBeenSet()
    {
        $candidateKey = ['candidate', 'key'];
        $relation = (new Relation())
            ->setCandidateKey($candidateKey);

        $this->assertSame($candidateKey, $relation->getCandidateKey());
    }

    public function testGetCandidateKeyReturnsCorrectStringIfStringHasBeenSet()
    {
        $candidateKey = 'candidate_key';
        $relation = (new Relation())
            ->setCandidateKey($candidateKey);

        $this->assertSame($candidateKey, $relation->getCandidateKey());
    }

    public function testGetTargetClassReturnsNullIfUnset()
    {
        $this->assertNull((new Relation())->getTargetClass());
    }

    public function testGetTargetClassReturnsCorrectTargetClassIfSet()
    {
        $targetClass = TestModel::class;
        $relation = (new Relation())
            ->setTargetClass($targetClass);

        $this->assertSame($targetClass, $relation->getTargetClass());
    }

    public function testSetTargetClassThrowsInvalidArgumentExceptionIfNotString()
    {
        $this->expectException(InvalidArgumentException::class);

        (new Relation())->setTargetClass(new TestModel());
    }

    public function testGetDefaultCandidateKeyReturnsEmptyArrayIfSourceModelsPrimaryKeyIsUnset()
    {
        $candidateKey = Relation::getDefaultCandidateKey(new TestModel());

        $this->assertTrue(is_array($candidateKey));
        $this->assertEmpty($candidateKey);
    }

    public function testGetDefaultCandidateKeyReturnsCorrectArrayIfSourceModelsPrimaryKeyIsAString()
    {
        $candidateKey = Relation::getDefaultCandidateKey(new TestModelWithPrimaryKey());

        $this->assertSame(['id'], $candidateKey);
    }

    public function testGetDefaultCandidateKeyReturnsCorrectArrayIfSourceModelsPrimaryKeyIsCompound()
    {
        $candidateKey = Relation::getDefaultCandidateKey(new TestModelWithCompoundPrimaryKey());

        $this->assertSame(['i', 'd'], $candidateKey);
    }

    public function testGetDefaultForeignKeyReturnsEmptyArrayIfSourceModelsPrimaryKeyIsUnset()
    {
        $foreignKey = Relation::getDefaultForeignKey(new TestModel());

        $this->assertTrue(is_array($foreignKey));
        $this->assertEmpty($foreignKey);
    }

    public function testGetDefaultForeignKeyReturnsCorrectArrayIfSourceModelsPrimaryKeyIsAString()
    {
        $foreignKey = Relation::getDefaultForeignKey(new TestModelWithPrimaryKey());

        $this->assertSame(['test_id'], $foreignKey);
    }

    public function testGetDefaultForeignKeyReturnsCorrectArrayIfSourceModelsPrimaryKeyIsCompound()
    {
        $foreignKey = Relation::getDefaultForeignKey(new TestModelWithCompoundPrimaryKey());

        $this->assertSame(['test_i', 'test_d'], $foreignKey);
    }

    public function testGetTargetReturnsAnInstanceOfTheTargetClass()
    {
        $relation = (new Relation())
            ->setTargetClass(TestModel::class);

        /** @noinspection PhpParamsInspection */
        $this->assertInstanceOf(TestModel::class, $relation->getTarget());
    }

    public function testGetTargetReturnsTheModelFromSetTarget()
    {
        $target = new TestModel();
        $relation = (new Relation())
            ->setTarget($target);

        $this->assertSame($target, $relation->getTarget());
    }

    public function testGetTargetPrefersTheModelFromSetTarget()
    {
        $target = new TestModel();
        $relation = (new Relation())
            ->setTarget($target)
            ->setTargetClass(TestModelWithPrimaryKey::class);

        $this->assertSame($target, $relation->getTarget());
    }

    public function testMultipleCallsToGetTargetAlwaysReturnsTheVerySameTargetInstance()
    {
        $relation = (new Relation())
            ->setTargetClass(TestModel::class);

        $target = $relation->getTarget();

        $this->assertSame($target, $relation->getTarget());
        $this->assertSame($target, $relation->getTarget());
    }
}
