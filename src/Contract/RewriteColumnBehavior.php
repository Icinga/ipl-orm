<?php

namespace ipl\Orm\Contract;

interface RewriteColumnBehavior extends RewriteFilterBehavior
{
    /**
     * Rewrite the given column
     *
     * The result must be returned otherwise (NULL is returned) the original column is kept as is.
     *
     * @param mixed $column
     * @param ?string $relation The absolute path of the model. For reference only, don't include it in the result
     *
     * @return mixed
     */
    public function rewriteColumn($column, ?string $relation = null);
}
