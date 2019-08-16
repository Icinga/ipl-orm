<?php

namespace ipl\Tests\Orm;

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
}
