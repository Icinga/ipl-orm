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
     * Collect all selectable columns from the given model
     *
     * @param Model $source
     *
     * @return array
     */
    public static function collectColumns(Model $source)
    {
        return array_merge((array) $source->getKeyName(), (array) $source->getColumns());
    }

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
            list($modelColumns, $foreignColumnMap) = $this->requireAndResolveColumns($columns);

            if (! empty($modelColumns) && ! empty($foreignColumnMap)) {
                // Only qualify columns if there is a relation to load
                $modelColumns = static::qualifyColumns($modelColumns, $tableName);
            }

            $select->columns($modelColumns);

            foreach ($foreignColumnMap as $relation => $foreignColumns) {
                $select->columns(static::qualifyColumns($foreignColumns, $this->with[$relation]->getName()));
            }
        } elseif (empty($this->with)) {
            // Don't qualify columns if we don't have any relation to load
            $select->columns(static::collectColumns($model));
        } else {
            $select->columns(static::qualifyColumns(static::collectColumns($model), $tableName));
        }

        foreach ($this->with as $relation) {
            foreach ($relation->resolve($model) as list($table, $condition)) {
                $select->join($table, $condition);
            }

            if (empty($columns)) {
                $select->columns(
                    static::qualifyColumns(static::collectColumns($relation->getTarget()), $relation->getName())
                );
            }
        }

        $select->limit($this->getLimit());
        $select->offset($this->getOffset());

        return $select;
    }

    /**
     * Create and return the hydrator
     *
     * @return Hydrator
     */
    public function createHydrator()
    {
        $model = $this->getModel();
        $hydrator = new Hydrator();
        $modelColumns = static::collectColumns($model);
        $hydrator->setColumnToPropertyMap(
            array_combine(array_keys(static::qualifyColumns($modelColumns, $model->getTableName())), $modelColumns)
        );

        foreach ($this->with as $relation) {
            $target = $relation->getTarget();
            $targetColumns = static::collectColumns($target);
            $hydrator->add(
                $relation->getName(),
                $relation->getTargetClass(),
                array_combine(array_keys(static::qualifyColumns($targetColumns, $relation->getName())), $targetColumns)
            );
        }

        return $hydrator;
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

    /**
     * Require and resolve columns
     *
     * Related models will be automatically added for eager-loading.
     *
     * @param array $columns
     *
     * @return array
     *
     * @throws \RuntimeException If a column does not exist
     */
    protected function requireAndResolveColumns(array $columns)
    {
        $tableName = $this->getModel()->getTableName();
        $modelColumns = [];
        $foreignColumnMap = [];

        foreach ($columns as $column) {
            $dot = strrpos($column, '.');

            switch (true) {
                /** @noinspection PhpMissingBreakStatementInspection */
                case $dot !== false:
                    $relation = substr($column, 0, $dot);
                    $column = substr($column, $dot + 1);

                    if ($relation !== $tableName) {
                        $this->with($relation);

                        $target = $this->with[$relation]->getTarget();

                        $resolved = &$foreignColumnMap[$relation];

                        break;
                    }
                    // Move to default
                default:
                    $target = $this->getModel();

                    $resolved = &$modelColumns;
            }

            $resolved[] = $column;

            if ($column === '*') {
                continue;
            }

            $columns = array_flip(static::collectColumns($target));

            if (! isset($columns[$column])) {
                throw new \RuntimeException(sprintf(
                    "Can't require column '%s' in model '%s'. Column not found.",
                    $column,
                    get_class($target)
                ));
            }
        }

        return [$modelColumns, $foreignColumnMap];
    }
}
