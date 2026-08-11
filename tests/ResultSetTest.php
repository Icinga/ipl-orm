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
            ['a', 'b', 'c'],
            $items
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
            ['a', 'b', 'c', 'a', 'b', 'c'],
            $items
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
            ['a', 'b', 'a', 'b'],
            $items
        );
    }

    public function testCountWithCacheDisabled(): void
    {
        $set = (new ResultSet(new ArrayIterator(['a', 'b', 'c'])))->disableCache();

        foreach ($set as $item) {
            // pass
        }

        $this->assertSame(3, $set->count());

        $limitedSet = (new ResultSet(new ArrayIterator(['a', 'b', 'c']), 2))->disableCache();

        foreach ($limitedSet as $item) {
            // pass
        }

        $this->assertSame(2, $limitedSet->count());
        $this->assertTrue($limitedSet->hasMore());

        $partialSet = (new ResultSet(new ArrayIterator(['a', 'b']), 3))->disableCache();

        foreach ($partialSet as $item) {
            // pass
        }

        $this->assertSame(2, $partialSet->count());
        $this->assertFalse($partialSet->hasMore());
    }

    public function testCountWithCacheEnabled(): void
    {
        $set = new ResultSet(new ArrayIterator(['a', 'b', 'c']));

        foreach ($set as $item) {
            // pass
        }

        $this->assertSame(3, $set->count());

        // During a subsequent iteration, count should be allowed
        foreach ($set as $item) {
            $this->assertSame(3, $set->count());
        }

        $limitedSet = new ResultSet(new ArrayIterator(['a', 'b', 'c']), 2);

        foreach ($limitedSet as $item) {
            // pass
        }

        $this->assertSame(2, $limitedSet->count());
        $this->assertTrue($limitedSet->hasMore());

        $partialSet = new ResultSet(new ArrayIterator(['a', 'b']), 3);

        foreach ($partialSet as $item) {
            // pass
        }

        $this->assertSame(2, $partialSet->count());
        $this->assertFalse($partialSet->hasMore());
    }

    public function testCountWithCacheDisabledBeforeIteration(): void
    {
        $set = (new ResultSet(new ArrayIterator(['a', 'b', 'c'])))->disableCache();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot count result set while it is not fully iterated');

        $set->count();
    }

    public function testLimitedCountWithCacheDisabledBeforeIteration(): void
    {
        $set = (new ResultSet(new ArrayIterator(['a', 'b', 'c']), 2))->disableCache();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot count result set while it is not fully iterated');

        $set->count();
    }

    public function testCountWithCacheDisabledDuringIteration(): void
    {
        $set = (new ResultSet(new ArrayIterator(['a', 'b', 'c'])))->disableCache();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot count result set while it is not fully iterated');

        foreach ($set as $item) {
            $set->count();
        }
    }

    public function testLimitedCountWithCacheDisabledDuringIteration(): void
    {
        $set = (new ResultSet(new ArrayIterator(['a', 'b', 'c']), 2))->disableCache();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot count result set while it is not fully iterated');

        foreach ($set as $item) {
            $set->count();
        }
    }

    public function testCountWithCacheBeforeIteration(): void
    {
        $set = new ResultSet(new ArrayIterator(['a', 'b', 'c']));

        $this->assertSame(3, $set->count());

        $result = [];
        foreach ($set as $item) {
            $result[] = $item;
        }

        $this->assertSame(['a', 'b', 'c'], $result);
    }

    public function testLimitedCountWithCacheBeforeIteration(): void
    {
        $set = new ResultSet(new ArrayIterator(['a', 'b', 'c']), 2);

        $this->assertSame(2, $set->count());

        $result = [];
        foreach ($set as $item) {
            $result[] = $item;
        }

        $this->assertSame(['a', 'b'], $result);
    }

    public function testCountWithCacheDuringIteration(): void
    {
        $set = new ResultSet(new ArrayIterator(['a', 'b', 'c']));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot count result set while it is not fully iterated');

        foreach ($set as $item) {
            $set->count();
        }
    }

    public function testLimitedCountWithCacheDuringIteration(): void
    {
        $set = new ResultSet(new ArrayIterator(['a', 'b', 'c']), 2);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot count result set while it is not fully iterated');

        foreach ($set as $item) {
            $set->count();
        }
    }

    public function testEmptySetHasCountZero(): void
    {
        $this->assertSame(0, (new ResultSet(new ArrayIterator([])))->count());
    }
}
