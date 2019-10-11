<?php

namespace ipl\Orm;

use ipl\Orm\Contract\BehaviorInterface;

class Behaviors
{
    /** @var BehaviorInterface[] Registered behaviors */
    protected $behaviors = [];

    /**
     * Add a behavior
     *
     * @param BehaviorInterface $behavior
     */
    public function add($behavior)
    {
        $this->behaviors[] = $behavior;
    }

    /**
     * Apply all behaviors on the given model
     *
     * @param Model $model
     */
    public function apply(Model $model)
    {
        foreach ($this->behaviors as $behavior) {
            $behavior->apply($model);
        }
    }
}
