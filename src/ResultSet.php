<?php

namespace ipl\Orm;

use ArrayIterator;
use BadMethodCallException;
use Generator;
use Iterator;
use Traversable;

/**
 * Dataset containing database rows
 */
class ResultSet implements Iterator
{
    /** @var ArrayIterator */
    protected $cache;

    /** @var bool Whether cache is disabled */
    protected $isCacheDisabled = false;

    /** @var Generator */
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
     * @param Traversable $traversable
     * @param ?int $limit
     * @param ?int $offset
     */
    public function __construct(Traversable $traversable, $limit = null, $offset = null)
    {
        $this->cache = new ArrayIterator();
        $this->generator = $this->yieldTraversable($traversable);
        $this->limit = $limit;
        $this->offset = $offset;
    }

    /**
     * Returns the current page calculated from the {@see ResultSet::$offset} and the {@see ResultSet::$pageSize}
     *
     * @return int
     * @throws BadMethodCallException if no {@see ResultSet::$pageSize} has been provided
     */
    public function getCurrentPage(): int
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

    /**
     * Sets the amount of items a page should contain (needed for pagination)
     *
     * @param ?int $size
     * @return $this
     */
    public function setPageSize(?int $size)
    {
        $this->pageSize = $size;

        return $this;
    }

    /**
     * Create a new result set from the given query
     *
     * @param Query $query
     *
     * @return static
     */
    public static function fromQuery(Query $query)
    {
        return new static($query->yieldResults(), $query->getLimit(), $query->getOffset());
    }

    /**
     * Do not cache query result
     *
     * ResultSet instance can only be iterated once
     *
     * @return $this
     */
    public function disableCache()
    {
        $this->isCacheDisabled = true;

        return $this;
    }

    /**
     * @return bool
     */
    public function hasMore()
    {
        return $this->generator->valid();
    }

    /**
     * @return bool
     */
    public function hasResult()
    {
        return $this->generator->valid();
    }

    #[\ReturnTypeWillChange]
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

    protected function advance()
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
     * @param Traversable $traversable
     * @return Generator
     */
    protected function yieldTraversable(Traversable $traversable)
    {
        foreach ($traversable as $key => $value) {
            yield $key => $value;
        }
    }
}
