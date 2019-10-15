<?php

namespace ipl\Orm\Contract;

use ipl\Orm\Model;

interface BehaviorInterface
{
    /**
     * Apply this behavior on the given model
     *
     * @param Model $model
     */
    public function apply(Model $model);
}
