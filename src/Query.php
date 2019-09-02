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
     * Qualify the given columns by the given table name
     *
     * @param array  $columns
     * @param string $tableName
     *
     * @return array
     */
    public static function qualifyColumns(array $columns, $tableName)
    {
        $qualified = [];

        foreach ($columns as $column) {
            $alias = $tableName . '_' . $column;
            $column = $tableName . '.' . $column;
            $qualified[$alias] = $column;
        }

        return $qualified;
    }

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
        $tableName = $model->getTableName();

        $select = (new Select())
            ->from($tableName);

        $columns = $this->getColumns();

        if (! empty($columns)) {
            $select->columns($columns);
        } elseif (empty($this->with)) {
            // Don't qualify columns if we don't have any relation to load
            $select
                ->columns($model->getKeyName() ?: [])
                ->columns($model->getColumns() ?: []);
                // `?: []` to support null for primary key and/or columns
        } else {
            $select
                ->columns(static::qualifyColumns((array) $model->getKeyName() ?: [], $tableName))
                ->columns(static::qualifyColumns($model->getColumns() ?: [], $tableName));
        }

        foreach ($this->with as $relation) {
            $targetClass = $relation->getTargetClass();
            /** @var Model $target */
            $target = new $targetClass();
            $targetTableName = $target->getTableName();
            $targetTableAlias = $relation->getName();
            $conditions = [];

            foreach ($relation->determineKeys($model) as $fk => $ck) {
                // Qualify keys
                $conditions[] = sprintf('%s.%s = %s.%s', $targetTableAlias, $fk, $tableName, $ck);
            }

            $select->join([$targetTableAlias => $targetTableName], $conditions);

            if (empty($columns)) {
                $select
                    ->columns(static::qualifyColumns((array) $target->getKeyName() ?: [], $targetTableAlias))
                    ->columns(static::qualifyColumns($target->getColumns() ?: [], $targetTableAlias));
            }
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
