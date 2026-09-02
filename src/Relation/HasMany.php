<?php

namespace ipl\Orm\Relation;

use ipl\Orm\Relation;

/**
 * One-to-many relationship
 *
 * @template TReverse of Relation = BelongsTo
 *
 * @extends Relation<TReverse>
 */
class HasMany extends Relation
{
    protected bool $isOne = false;

    protected ?string $reverseClass = BelongsTo::class;
}
