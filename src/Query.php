<?php

namespace ipl\Orm;

use InvalidArgumentException;
use ipl\Orm\Relation\BelongsToMany;
use ipl\Orm\Relation\HasMany;
use ipl\Orm\Relation\Junction;
use ipl\Sql\Connection;
use ipl\Sql\LimitOffset;
use ipl\Sql\LimitOffsetInterface;
use ipl\Sql\Select;
use ipl\Stdlib\Contract\PaginationInterface;
use function ipl\Stdlib\get_php_type;

/**
 * Represents a database query which is associated to a model and a database connection.
 */
class Query implements LimitOffsetInterface, PaginationInterface, \IteratorAggregate
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

    /** @var Select Base SELECT query */
    protected $selectBase;

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
        // Don't fail if Model::getColumns() also contains the primary key columns
        return array_unique(array_merge((array) $source->getKeyName(), (array) $source->getColumns()));
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
     * Get the model's relations
     *
     * @return Relations
     */
    public function getRelations()
    {
        $this->ensureRelationsCreated();

        return $this->relations;
    }

    /**
     * Get the SELECT base query
     *
     * @return Select
     */
    public function getSelectBase()
    {
        if ($this->selectBase === null) {
            $this->selectBase = new Select();
        }

        return $this->selectBase;
    }

    /**
     * Get the relations to eager load
     *
     * @return Relation[]
     */
    public function getWith()
    {
        return $this->with;
    }

    /**
     * Add a relation to eager load
     *
     * @param string|array $relations
     *
     * @return $this
     */
    public function with($relations)
    {
        $model = $this->getModel();
        $tableName = $model->getTableName();

        $relationStorage = new \SplObjectStorage();
        $relationStorage->attach($model, $this->getRelations());

        foreach ((array) $relations as $relation) {
            $current = [];
            $subject = $model;

            foreach (explode('.', $relation) as $name) {
                $current[] = $name;

                if ($name === $tableName) {
                    continue;
                }

                $path = implode('.', $current);

                if (isset($this->with[$path])) {
                    $subject = $this->with[$path]->getTarget();
                    continue;
                }

                if ($relationStorage->contains($subject)) {
                    $subjectRelations = $relationStorage[$subject];
                } else {
                    $subjectRelations = new Relations();
                    $subject->createRelations($subjectRelations);
                    $relationStorage->attach($subject, $subjectRelations);
                }

                if (! $subjectRelations->has($name)) {
                    throw new InvalidArgumentException(sprintf(
                        "Can't join relation '%s' in model '%s'. Relation not found.",
                        $name,
                        get_class($subject)
                    ));
                }

                $this->with[$path] = $subjectRelations->get($name)->setSource($subject);

                $subject = $this->with[$path]->getTarget();
            }
        }

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

        $select = clone $this->getSelectBase();
        $select->from($tableName);

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
            foreach ($relation->resolve($relation->getSource() ?: $model) as list($table, $condition)) {
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
        $hydrator->setColumnToPropertyMap(array_combine(
        empty($this->with) // Only qualify columns if we loaded relations
                ? $modelColumns
                : array_keys(static::qualifyColumns($modelColumns, $model->getTableName())),
            $modelColumns
        ));

        foreach ($this->with as $relation) {
            $target = $relation->getTarget();
            $targetColumns = static::collectColumns($target);
            $hydrator->add(
                $relation->getName(),
                $relation->getTargetClass(),
                array_combine(array_keys(static::qualifyColumns($targetColumns, $relation->getName())), $targetColumns)
            );
        }

        $defaults = [];
        foreach ($this->getRelations() as $relation) {
            $name = $relation->getName();

            if (! isset($this->with[$name])) {
                $defaults[$name] = function (Model $model) use ($name) {
                    return $this->derive($name, $model)->execute();
                };
            }
        }
        if (! empty($defaults)) {
            $hydrator->setDefaults($defaults);
        }

        return $hydrator;
    }

    /**
     * Derive a new query to load the specified relation from a concrete model
     *
     * @param string $relation
     * @param Model  $source
     *
     * @return static
     *
     * @throws InvalidArgumentException If the relation with the given name does not exist
     */
    public function derive($relation, Model $source)
    {
        $modelRelations = $this->getRelations();

        if (! $modelRelations->has($relation)) {
            throw new InvalidArgumentException(sprintf(
                "Can't join relation '%s' in model '%s'. Relation not found.",
                $relation,
                get_class($this->getModel())
            ));
        }

        $relation = $modelRelations->get($relation);
        $target = $relation->getTarget();
        $conditionsTarget = $target->getTableName();
        $query = (new Query())
            ->setModel($target)
            ->setDb($this->getDb());

        if ($relation instanceof BelongsToMany) {
            $through = $relation->getThrough();

            if (class_exists($through)) {
                $junction = new $through();

                if (! $junction instanceof Model) {
                    throw new InvalidArgumentException(sprintf(
                        'Junction model class must be an instance of %s, %s given',
                        Model::class,
                        get_php_type($junction)
                    ));
                }
            } else {
                $junction = (new Junction())
                    ->setTableName($through);
            }

            $toJunction = (new HasMany())
                ->setName($junction->getTableName())
                ->setTarget($junction)
                ->setTargetClass(get_class($junction));

            $conditionsTarget = $junction->getTableName();

            $query->with[$conditionsTarget] = $toJunction;
        }

        $conditions = $relation->determineKeys($source);
        $select = $query->getSelectBase();
        foreach ($conditions as $fk => $ck) {
            $select->where(["$conditionsTarget.$fk = ?" => $source->$ck]);
        }

        return $query;
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
     * Execute the query
     *
     * @return \Generator
     */
    public function execute()
    {
        $select = $this->assembleSelect();
        $stmt = $this->getDb()->select($select);
        $stmt->setFetchMode(\PDO::FETCH_ASSOC);

        $hydrator = $this->createHydrator();
        $modelClass = get_class($this->getModel());

        foreach ($stmt as $row) {
            yield $hydrator->hydrate($row, new $modelClass());
        }
    }

    /**
     * Fetch and return the first result
     *
     * @return Model|null Null in case there's no result
     */
    public function first()
    {
        return $this->execute()->current();
    }

    public function count()
    {
        return $this->getDb()->select($this->assembleSelect()->getCountQuery())->fetchColumn(0);
    }

    public function getIterator()
    {
        return $this->execute();
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
