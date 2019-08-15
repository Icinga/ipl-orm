<?php

namespace ipl\Orm;

/**
 * Represents a database query which is associated to a model and a database connection.
 */
class Query
{
    /** @var Model Model to query */
    protected $model;

    /**
     * Get the model to query
     *
     * @return Model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Set the model to query
     *
     * @param $model
     *
     * @return $this
     */
    public function setModel(Model $model)
    {
        $this->model = $model;

        return $this;
    }
}
