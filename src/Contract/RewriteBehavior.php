<?php

namespace ipl\Orm\Contract;

interface RewriteBehavior extends RewriteFilterBehavior
{
    /**
     * Rewrite the given column path
     *
     * The result must be returned otherwise (NULL is returned) the original path is kept as is.
     *
     * @param string $path
     * @param string $relation The absolute path (with a trailing dot) of the model
     *
     * @return string|null
     */
    public function rewritePath($path, $relation = null);
}
