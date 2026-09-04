<?php

namespace ipl\Orm\Relation;

use ipl\Orm\Relation;

/**
 * One-to-one relationship
 *
 * @template TReverse of Relation = BelongsTo
 *
 * @extends Relation<TReverse>
 */
class HasOne extends Relation
{
    protected ?string $reverseClass = BelongsTo::class;
}
