<?php

namespace ipl\Orm;

use ArrayIterator;
use Iterator;
use Traversable;

class ResultSet implements Iterator
{
    protected $cache;

    protected $generator;

    protected $limit;

    protected $position;

    public function __construct(Traversable $traversable, $limit = null)
    {
        $this->cache = new ArrayIterator();
        $this->generator = $this->yieldTraversable($traversable);
        $this->limit = $limit;

        $this->advance();
    }

    public function hasMore()
    {
        return $this->generator->valid();
    }

    public function hasResult()
    {
        return $this->generator->valid();
    }

    public function current()
    {
        return $this->cache->current();
    }

    public function next()
    {
        $this->cache->next();

        if (! $this->cache->valid()) {
            $this->generator->next();
            $this->advance();
        }

        ++$this->position;
    }

    public function key()
    {
        return $this->cache->key();
    }

    public function valid()
    {
        if ($this->limit !== null && $this->position === $this->limit) {
            return false;
        }

        return $this->cache->valid() || $this->generator->valid();
    }

    public function rewind()
    {
        $this->cache->rewind();

        if ($this->position === null) {
            $this->advance();
        }

        $this->position = 0;
    }

    protected function advance()
    {
        if (! $this->generator->valid()) {
            return;
        }

        $this->cache[$this->generator->key()] = $this->generator->current();
    }

    protected function yieldTraversable(Traversable $traversable)
    {
        foreach ($traversable as $key => $value) {
            yield $key => $value;
        }
    }
}