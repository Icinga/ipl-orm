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

    public function testResultWithCacheDisabled()
    {
        $set = (new ResultSet(new ArrayIterator(['a', 'b', 'c'])))->disableCache();

        $items = [];
        foreach ($set as $item) {
            $items[] = $item;
        }

        // When cache disabled, $set can be iterated only once, so this loop will be skipped
        foreach ($set as $item) {
            $items[] = $item;
        }

        $this->assertEquals(
            $items,
            ['a', 'b', 'c']
        );
    }

    public function testResultWithCacheEnabled()
    {
        $set = (new ResultSet(new ArrayIterator(['a', 'b', 'c'])));

        $items = [];
        foreach ($set as $item) {
            $items[] = $item;
        }

        foreach ($set as $item) {
            $items[] = $item;
        }

        $this->assertEquals(
            $items,
            ['a', 'b', 'c', 'a', 'b', 'c']
        );
    }
}
