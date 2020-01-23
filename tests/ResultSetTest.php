<?php

namespace ipl\Tests\Orm;

use ArrayIterator;
use ipl\Orm\ResultSet;
use PHPUnit\Framework\TestCase;

class ResultSetTest extends TestCase
{
    public function testResultIsProperlyAdvancedOnPhp56()
    {
        $set = new ResultSet(new ArrayIterator(['a', 'b', 'c']));

        $items = [];
        foreach ($set as $item) {
            $items[] = $item;
        }

        $this->assertEquals(
            ['a', 'b', 'c'],
            $items,
            'ArrayIterator::offsetSet() (used in ResultSet::advance()) does not seek automatically on PHP 5.6'
        );
    }
}
