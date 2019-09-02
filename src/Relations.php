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
     * Get whether a relation with the given name exists
     *
     * @param string $name
     *
     * @return bool
     */
    public function has($name)
    {
        return isset($this->relations[$name]);
    }

    /**
     * Get the relation with the given name
     *
     * @param string $name
     *
     * @return Relation
     *
     * @throws \InvalidArgumentException If the relation with the given name does not exist
     */
    public function get($name)
    {
        $this->assertRelationExists($name);

        return $this->relations[$name];
    }

    /**
     * Create and add a new relation with the given name and target model class
     *
     * @param string $name        Name of the relation
     * @param string $targetClass Target model class
     *
     * @return Relation
     *
     * @throws \InvalidArgumentException If a relation with the given name already exists
     * @throws \InvalidArgumentException If the target model class is not of type string
     */
    public function create($name, $targetClass)
    {
        $this->assertRelationDoesNotYetExist($name);

        $relation = (new Relation())
            ->setName($name)
            ->setTargetClass($targetClass);

        $this->relations[$name] = $relation;

        return $relation;
    }

    /**
     * Throw exception if a relation with the given name already exists
     *
     * @param string $name
     */
    protected function assertRelationDoesNotYetExist($name)
    {
        if ($this->has($name)) {
            throw new \InvalidArgumentException("Relation '$name' already exists");
        }
    }

    /**
     * Throw exception if a relation with the given name does not exist
     *
     * @param string $name
     */
    protected function assertRelationExists($name)
    {
        if (! $this->has($name)) {
            throw new \InvalidArgumentException("Can't access relation '$name'. Relation not found");
        }
    }
}
