<?php

namespace ipl\Orm;

use ipl\Sql\Connection;
use ipl\Sql\LimitOffset;
use ipl\Sql\LimitOffsetInterface;
use ipl\Sql\Select;

/**
 * Represents a database query which is associated to a model and a database connection.
 */
class Query implements LimitOffsetInterface
{
    use LimitOffset;

    /** @var Connection Database connection */
    protected $db;

    /** @var Model Model to query */
    protected $model;

    /** @var array Columns to select from the model */
    protected $columns = [];

    /** @var Relations Model's relations */
    protected $relations;

    /** @var Relation[] Relations to eager load */
    protected $with = [];

    /**
     * Get the database connection
     *
     * @return Connection
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * Set the database connection
     *
     * @param Connection $db
     *
     * @return $this
     */
    public function setDb(Connection $db)
    {
        $this->db = $db;

        return $this;
    }

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

    /**
     * Get the columns to select from the model
     *
     * @return array
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Set columns to select from the model
     *
     * Multiple calls to this method will not overwrite the previous set columns but append the columns to the query.
     *
     * @param string|array $columns The column(s) to select
     *
     * @return $this
     */
    public function columns($columns)
    {
        $this->columns = array_merge($this->columns, (array) $columns);

        return $this;
    }

    /**
     * Add a relation to eager load
     *
     * @param string $relation
     *
     * @return $this
     */
    public function with($relation)
    {
        $this->ensureRelationsCreated();

        if (! $this->relations->has($relation)) {
            $model = $this->getModel();

            throw new \InvalidArgumentException(sprintf(
                "Can't join relation '%s' in model '%s'. Relation not found.",
                $relation,
                get_class($model)
            ));
        }

        $this->with[$relation] = $this->relations->get($relation);

        return $this;
    }

    /**
     * Assemble and return the SELECT query
     *
     * @return Select
     */
    public function assembleSelect()
    {
        $model = $this->getModel();

        $select = (new Select())
            ->from($model->getTableName());

        $columns = $this->getColumns();

        if (! empty($columns)) {
            $select->columns($columns);
        } else {
            $select
                ->columns($model->getKeyName() ?: []) // `?: []` to support null for primary key and/or columns
                ->columns($model->getColumns() ?: []);
        }

        $select->limit($this->getLimit());
        $select->offset($this->getOffset());

        return $select;
    }

    /**
     * Ensure that the model's relations have been created
     *
     * @return $this
     */
    public function ensureRelationsCreated()
    {
        if ($this->relations === null) {
            $relations = new Relations();
            $this->getModel()->createRelations($relations);
            $this->relations = $relations;
        }

        return $this;
    }
}
