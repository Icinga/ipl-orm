<?php

namespace ipl\Orm;

use ArrayIterator;
use BadMethodCallException;
use Generator;
use Iterator;
use Traversable;

/**
 * Dataset containing database rows
 *
 * @implements Iterator<int, mixed>
 */
class ResultSet implements Iterator
{
    /** @var ArrayIterator<int, mixed> */
    protected $cache;

    /** @var bool Whether cache is disabled */
    protected $isCacheDisabled = false;

    /** @var Generator<int, mixed, mixed, mixed> */
    protected $generator;

    /** @var ?int */
    protected $limit;

    /** @var ?int */
    protected $position;

    /** @var ?int */
    protected $offset;

    /** @var ?int */
    protected $pageSize;

    /**
     * @param Traversable<int, mixed> $traversable
     * @param ?int $limit
     * @param ?int $offset
     */
    public function __construct(Traversable $traversable, ?int $limit = null, ?int $offset = null)
    {
        $this->cache = new ArrayIterator();
        $this->generator = $this->yieldTraversable($traversable);
        $this->limit = $limit;
        $this->offset = $offset;
    }

    /**
     * Create a new result set from the given query
     *
     * @param Query $query
     *
     * @return ResultSet
     */
    public static function fromQuery(Query $query): ResultSet
    {
        return new static($query->yieldResults(), $query->getLimit(), $query->getOffset());
    }

    /**
     * Do not cache query result
     *
     * ResultSet instance can only be iterated once
     *
     * @return ResultSet
     */
    public function disableCache(): ResultSet
    {
        $this->isCacheDisabled = true;

        return $this;
    }

    public function hasMore(): bool
    {
        return $this->generator->valid();
    }

    public function hasResult(): bool
    {
        return $this->generator->valid();
    }

    /**
     * @return mixed
     */
    public function current()
    {
        if ($this->position === null) {
            $this->advance();
        }

        return $this->isCacheDisabled ? $this->generator->current() : $this->cache->current();
    }

    public function next(): void
    {
        if (! $this->isCacheDisabled) {
            $this->cache->next();
        }

        if ($this->isCacheDisabled || ! $this->cache->valid()) {
            $this->generator->next();
            $this->advance();
        } else {
            $this->position += 1;
        }
    }

    public function key(): ?int
    {
        if ($this->position === null) {
            $this->advance();
        }

        return $this->isCacheDisabled ? $this->generator->key() : $this->cache->key();
    }

    public function valid(): bool
    {
        if ($this->limit !== null && $this->position === $this->limit) {
            return false;
        }

        return $this->cache->valid() || $this->generator->valid();
    }

    public function rewind(): void
    {
        if (! $this->isCacheDisabled) {
            $this->cache->rewind();
        }

        if ($this->position === null) {
            $this->advance();
        } else {
            $this->position = 0;
        }
    }

    protected function advance(): void
    {
        if (! $this->generator->valid()) {
            return;
        }

        if (! $this->isCacheDisabled) {
            $this->cache[$this->generator->key()] = $this->generator->current();

            // Only required on PHP 5.6, 7+ does it automatically
            $this->cache->seek($this->generator->key());
        }

        if ($this->position === null) {
            $this->position = 0;
        } else {
            $this->position += 1;
        }
    }

    /**
     * @param Traversable<int, mixed> $traversable
     * @return Generator
     */
    protected function yieldTraversable(Traversable $traversable): Generator
    {
        foreach ($traversable as $key => $value) {
            yield $key => $value;
        }
    }

    /**
     * Sets the amount of items a page should contain (only needed for pagination)
     *
     * @param ?int $size
     * @return void
     */
    public function setPageSize(?int $size): void
    {
        $this->pageSize = $size;
    }

    /**
     * Returns the current page calculated from the {@see ResultSet::$offset} and the {@see ResultSet::$pageSize}
     *
     * @return int
     * @throws BadMethodCallException if no {@see ResultSet::$pageSize} has been provided
     */
    protected function getCurrentPage(): int
    {
        if ($this->pageSize) {
            if ($this->offset && $this->offset > $this->pageSize) {
                // offset is not on the first page anymore
                return intval(floor($this->offset / $this->pageSize));
            }

            // no offset defined or still on page 1
            return 1;
        }

        throw new BadMethodCallException(`The 'pageSize' property has not been set. Cannot calculate pages.`);
    }
}
