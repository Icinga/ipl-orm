<?php

namespace ipl\Orm;

/**
 * Collection of a model's relations.
 */
class Relations
{
    /** @var Relation[] */
    protected $relations = [];

    /**
     * Create and add a new relation with the given name and target model class
     *
     * @param string $name        Name of the relation
     * @param string $targetClass Target model class
     *
     * @return Relation
     *
     * @throws \InvalidArgumentException If the target model class is not of type string
     */
    public function create($name, $targetClass)
    {
        $relation = (new Relation())
            ->setName($name)
            ->setTargetClass($targetClass);

        $this->relations[$name] = $relation;

        return $relation;
    }
}
