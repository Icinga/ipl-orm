<?php

namespace ipl\Tests\Orm;

use ArrayIterator;
use BadMethodCallException;
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

    public function testResultWithCacheEnabledWithLimit()
    {
        $set = (new ResultSet(new ArrayIterator(['a', 'b', 'c']), 2));

        $items = [];
        foreach ($set as $item) {
            $items[] = $item;
        }

        foreach ($set as $item) {
            $items[] = $item;
        }

        $this->assertEquals(
            $items,
            ['a', 'b', 'a', 'b']
        );
    }

    public function testResultPaging()
    {
        $set = (new ResultSet(new ArrayIterator(['a', 'b', 'c', 'd', 'e', 'f', 'g'])))
            ->setPageSize(2);

        $count = 0;
        foreach ($set as $item) {
            ++$count;

            if ($count > 2) {
                if ($count % 2 === 0) {
                    // a multiple of two, page should equal to count / 2
                    $this->assertEquals(
                        $set->getCurrentPage(),
                        $count / 2
                    );
                } elseif ($count % 2 === 1) {
                    $this->assertEquals(
                        $set->getCurrentPage(),
                        intval(ceil($count / 2))
                    );
                }
            } else {
                $this->assertEquals(
                    $set->getCurrentPage(),
                    1
                );
            }
        }
    }

    public function testResultPagingWithoutPageSize()
    {
        $this->expectException(BadMethodCallException::class);

        $set = (new ResultSet(new ArrayIterator(['a', 'b', 'c', 'd', 'e', 'f', 'g'])));

        foreach ($set as $_) {
            // this raises an exception as no page size has been set
            $set->getCurrentPage();
        }
    }

    public function testResultPagingWithOffset()
    {
        $set = (new ResultSet(new ArrayIterator(['d', 'e', 'f', 'g', 'h', 'i', 'j']), null, 3))
            ->setPageSize(2);

        $count = 0;
        foreach ($set as $_) {
            ++$count;

            $offsetCount = $count + 3;
            if ($offsetCount % 2 === 0) {
                // a multiple of two, page should equal to offsetCount / 2
                $this->assertEquals(
                    $set->getCurrentPage(),
                    $offsetCount / 2
                );
            } elseif ($offsetCount % 2 === 1) {
                $this->assertEquals(
                    $set->getCurrentPage(),
                    intval(ceil($offsetCount / 2))
                );
            }
        }
    }

    public function testResultPagingBeforeIteration()
    {
        $set = (new ResultSet(new ArrayIterator(['a', 'b', 'c', 'd', 'e', 'f', 'g'])))
            ->setPageSize(2);

        $this->assertEquals(
            $set->getCurrentPage(),
            1
        );
    }
}
