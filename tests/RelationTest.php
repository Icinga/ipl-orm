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
}
