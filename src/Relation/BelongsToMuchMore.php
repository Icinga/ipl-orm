<?php

namespace ipl\Orm\Relation;

use Generator;
use ipl\Orm\Relation;
use ipl\Stdlib\Str;

class BelongsToMuchMore extends Relation
{
    protected $isOne = false;

    protected $relations;

    protected $skipParts;

    public function setRelations(Generator $relations, $skipParts = null)
    {
        $this->relations = $relations;
        $this->skipParts = $skipParts;

        return $this;
    }

    public function resolve()
    {
        foreach ($this->relations as $path => $relation) {
            if ($this->skipParts && Str::startsWith($this->skipParts, $path)) {
                continue;
            }

            foreach ($relation->resolve() as $resolved) {
                yield $resolved;
            }
        }
    }
}
