<?php

namespace ipl\Orm;

use ipl\Sql\Connection;
use ipl\Sql\Select;

/**
 * Represents a database query which is associated to a model and a database connection.
 */
class Query
{
    /** @var Connection Database connection */
    protected $db;

    /** @var Model Model to query */
    protected $model;

    /** @var array Columns to select from the model */
    protected $columns = [];

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
     * Assemble and return the SELECT query
     *
     * @return Select
     */
    public function assembleSelect()
    {
        $model = $this->getModel();

        $select = (new Select())
            ->from($model->getTableName())
            ->columns($model->getKeyName() ?: []) // `?: []` to support null for primary key and/or columns
            ->columns($model->getColumns() ?: []);

        return $select;
    }
}
