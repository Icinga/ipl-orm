<?php

namespace ipl\Orm\Relation;

use ipl\Orm\Relation;

/**
 * Inverse of a one-to-one or one-to-many relationship
 *
 * @template TReverse of Relation = HasMany
 *
 * @extends Relation<TReverse>
 */
class BelongsTo extends Relation
{
    protected bool $inverse = true;

    protected ?string $reverseClass = HasMany::class;
}
