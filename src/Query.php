<?php

namespace ipl\Orm;

use ArrayObject;
use Generator;
use InvalidArgumentException;
use ipl\Orm\Relation\BelongsToMany;
use ipl\Orm\Relation\HasMany;
use ipl\Orm\Relation\Junction;
use ipl\Sql\Connection;
use ipl\Sql\LimitOffset;
use ipl\Sql\LimitOffsetInterface;
use ipl\Sql\Select;
use ipl\Stdlib\Contract\PaginationInterface;
use OutOfBoundsException;
use SplObjectStorage;
use function ipl\Stdlib\get_php_type;

/**
 * Represents a database query which is associated to a model and a database connection.
 */
class Query implements LimitOffsetInterface, PaginationInterface, \IteratorAggregate
{
    use LimitOffset;

    /** @var int Count cache */
    protected $count;

    /** @var Connection Database connection */
    protected $db;

    /** @var Model Model to query */
    protected $model;

    /** @var array Columns to select from the model */
    protected $columns = [];

    /** @var bool Whether to peek ahead for more results */
    protected $peekAhead = false;

    /** @var SplObjectStorage Cached model behaviors */
    protected $behaviorStorage;

    /** @var SplObjectStorage Cached model relations */
    protected $relationStorage;

    /** @var Resolver Column and relation resolver */
    protected $resolver;

    /** @var Select Base SELECT query */
    protected $selectBase;

    /** @var Relation[] Relations to eager load */
    protected $with = [];

    public function __construct()
    {
        $this->behaviorStorage = new SplObjectStorage();
        $this->relationStorage = new SplObjectStorage();
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
     * Get the model's behaviors
     *
     * @param Model $model If not given, the base model's behaviors are returned
     *
     * @return Behaviors
     */
    public function getBehaviors(Model $model = null)
    {
        if ($model === null) {
            $model = $this->getModel();
        }

        if (! $this->behaviorStorage->contains($model)) {
            $behaviors = new Behaviors();
            $model->createBehaviors($behaviors);
            $this->behaviorStorage->attach($model, $behaviors);
        }

        return $this->behaviorStorage[$model];
    }

    /**
     * Get the model's relations
     *
     * @param Model $model If not given, the base model's relations are returned
     *
     * @return Relations
     */
    public function getRelations(Model $model = null)
    {
        if ($model === null) {
            $model = $this->getModel();
        }

        if (! $this->relationStorage->contains($model)) {
            $relations = new Relations();
            $model->createRelations($relations);
            $this->relationStorage->attach($model, $relations);
        }

        return $this->relationStorage[$model];
    }

    /**
     * Get the query's resolver
     *
     * @return Resolver
     */
    public function getResolver()
    {
        if ($this->resolver === null) {
            $this->resolver = (new Resolver())
                ->setAlias($this->getModel(), $this->getModel()->getTableName());
        }

        return $this->resolver;
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

            $tableName = $this->getModel()->getTableName();

            $aliasPrefix = $this->getResolver()->getAliasPrefix();
            if ($aliasPrefix !== null) {
                $this->selectBase->from([$aliasPrefix . $tableName => $tableName]);
            } else {
                $this->selectBase->from($tableName);
            }
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
        $resolver = $this->getResolver();
        $tableName = $model->getTableName();

        foreach ((array) $relations as $relation) {
            $current = [$tableName];
            $subject = $model;
            $segments = explode('.', $relation);

            if ($segments[0] === $tableName) {
                array_shift($segments);
            }

            foreach ($segments as $name) {
                $current[] = $name;
                $path = implode('.', $current);

                if (isset($this->with[$path])) {
                    $subject = $this->with[$path]->getTarget();
                    continue;
                }

                $subjectRelations = $this->getRelations($subject);
                if (! $subjectRelations->has($name)) {
                    throw new InvalidArgumentException(sprintf(
                        "Can't join relation '%s' in model '%s'. Relation not found.",
                        $name,
                        get_class($subject)
                    ));
                }

                $this->with[$path] = $subjectRelations->get($name)->setSource($subject);

                $subject = $this->with[$path]->getTarget();

                $resolver->setAlias($subject, str_replace('.', '_', $path));
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
        $columns = $this->getColumns();
        $model = $this->getModel();
        $select = clone $this->getSelectBase();
        $resolver = $this->getResolver();

        if (! empty($columns)) {
            $resolved = $this->groupColumnsByTarget($resolver->requireAndResolveColumns($this, $columns));

            if ($resolved->contains($model)) {
                $select->columns(
                    $resolver->qualifyColumns($resolved[$model]->getArrayCopy(), $resolver->getAlias($model))
                );
                $resolved->detach($model);
            }

            foreach ($resolved as $target) {
                $select->columns(
                    $resolver->qualifyColumnsAndAliases(
                        $resolved[$target]->getArrayCopy(),
                        $resolver->getAlias($target)
                    )
                );
            }
        } else {
            $select->columns(
                $resolver->qualifyColumns($resolver->getSelectColumns($model), $resolver->getAlias($model))
            );
        }

        $aggregateColumns = $model->getAggregateColumns();
        if ($aggregateColumns === true) {
            $select->groupBy(
                $resolver->qualifyColumns((array) $model->getKeyName(), $resolver->getAlias($model))
            );
        } elseif (! empty($aggregateColumns)) {
            $aggregateColumns = array_flip($aggregateColumns);
            foreach ($select->getColumns() as $alias => $column) {
                if (isset($aggregateColumns[$alias])) {
                    $select->groupBy(
                        $resolver->qualifyColumns((array) $model->getKeyName(), $resolver->getAlias($model))
                    );

                    break;
                }
            }
        }

        foreach ($this->with as $relation) {
            foreach ($relation->resolve() as list($source, $target, $conditions)) {
                $condition = [];

                try {
                    $targetTableAlias = $resolver->getAlias($target);
                } catch (OutOfBoundsException $e) {
                    // TODO(el): This is just a quick fix for many-to-many relations where the alias of the junction
                    // model is unknown yet
                    $targetTableAlias = $resolver->getAlias($source) . '_' . $target->getTableName();
                    $resolver->setAlias($target, $targetTableAlias);
                }

                $sourceTableAlias = $resolver->getAlias($source);

                foreach ($conditions as $fk => $ck) {
                    $condition[] = sprintf(
                        '%s.%s = %s.%s',
                        $targetTableAlias,
                        $fk,
                        $sourceTableAlias,
                        $ck
                    );
                }

                $table = [$targetTableAlias => $target->getTableName()];

                switch ($relation->getJoinType()) {
                    case 'LEFT':
                        $select->joinLeft($table, $condition);

                        break;
                    case 'RIGHT':
                        $select->joinRight($table, $condition);

                        break;
                    case 'INNER':
                    default:
                        $select->join($table, $condition);
                }
            }

            if (empty($columns)) {
                $select->columns(
                    $resolver->qualifyColumnsAndAliases(
                        $resolver->getSelectColumns($relation->getTarget()), $resolver->getAlias($relation->getTarget())
                    )
                );
            }
        }

        if ($this->hasLimit()) {
            $limit = $this->getLimit();

            if ($this->peekAhead) {
                ++$limit;
            }

            $select->limit($limit);
        }
        if ($this->hasOffset()) {
            $select->offset($this->getOffset());
        }

        $this->order($select);

        return $select;
    }

    /**
     * Create and return the hydrator
     *
     * @return Hydrator
     */
    public function createHydrator()
    {
        $hydrator = new Hydrator();
        $model = $this->getModel();
        $resolver = $this->getResolver();

        $modelColumns = $resolver->getSelectableColumns($model);

        $hydrator->setColumnToPropertyMap(array_combine(
            $modelColumns,
            $modelColumns
        ));

        foreach ($this->with as $path => $relation) {
            $target = $relation->getTarget();
            $targetColumns = $resolver->getSelectableColumns($target);

            $hydrator->add(
                explode('.', $path, 2)[1],
                $relation->getName(),
                $relation->getTargetClass(),
                array_combine(
                    array_keys($resolver->qualifyColumnsAndAliases($targetColumns, $resolver->getAlias($relation->getTarget()))),
                    $targetColumns
                ),
                $this->getBehaviors($target)
            );
        }

        $defaults = [];
        foreach ($this->getRelations() as $relation) {
            $name = $relation->getName();
            $isOne = $relation->isOne();

            if (! isset($this->with[$name])) {
                $defaults[$name] = function (Model $model) use ($name, $isOne) {
                    $query = $this->derive($name, $model);
                    return $isOne ? $query->first() : $query;
                };
            }
        }
        if (! empty($defaults)) {
            $hydrator->setDefaults($defaults);
        }

        $hydrator->setBehaviors($this->getBehaviors());

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
            ->setDb($this->getDb())
            ->setModel($target);
        $resolver = $query
            ->getResolver()
            ->setAlias($target, $conditionsTarget);

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

            // Override $conditionsTarget
            $conditionsTarget = $junction->getTableName();

            $resolver->setAlias($junction, $conditionsTarget);

            $toJunction = (new HasMany())
                ->setName($junction->getTableName())
                ->setSource($target)
                ->setTarget($junction)
                ->setTargetClass(get_class($junction));

            $query->with[$this->getResolver()->qualifyPath($conditionsTarget, $source->getTableName())] = $toJunction;
        }

        $conditions = $relation->determineKeys($source);
        $select = $query->getSelectBase();
        foreach ($conditions as $fk => $ck) {
            $select->where(["$conditionsTarget.$fk = ?" => $source->$ck]);
        }

        return $query;
    }

    /**
     * Dump the query
     *
     * @return array
     */
    public function dump()
    {
        return $this->getDb()->getQueryBuilder()->assembleSelect($this->assembleSelect());
    }

    /**
     * Execute the query
     *
     * @return ResultSet
     */
    public function execute()
    {
        return new ResultSet($this->yieldResults(), $this->getLimit());
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

    /**
     * Set whether to peek ahead for more results
     *
     * Enabling this causes the current query limit to be increased by one. The potential extra row being yielded will
     * be removed from the result set. Note that this only applies when fetching multiple results of limited queries.
     *
     * @param bool $peekAhead
     *
     * @return $this
     */
    public function peekAhead($peekAhead = true)
    {
        $this->peekAhead = (bool) $peekAhead;

        return $this;
    }

    /**
     * Yield the query's results
     *
     * @return \Generator
     */
    public function yieldResults()
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

    public function count()
    {
        if ($this->count === null) {
            $this->count = $this->getDb()->select($this->assembleSelect()->getCountQuery())->fetchColumn(0);
        }

        return $this->count;
    }

    public function getIterator()
    {
        return $this->execute();
    }

    /**
     * Group columns from {@link Resolver::requireAndResolveColumns()} by target models
     *
     * @param Generator $columns
     *
     * @return SplObjectStorage
     */
    protected function groupColumnsByTarget(Generator $columns)
    {
        $columnStorage = new SplObjectStorage();

        foreach ($columns as list($target, $alias, $column)) {
            if (! $columnStorage->contains($target)) {
                $resolved = new ArrayObject();
                $columnStorage->attach($target, $resolved);
            } else {
                $resolved = $columnStorage[$target];
            }

            if (is_int($alias)) {
                $resolved[] = $column;
            } else {
                $resolved[$alias] = $column;
            }
        }

        return $columnStorage;
    }

    /**
     * Resolve, require and apply ORDER BY columns
     *
     * @param Select $select
     *
     * @return $this
     */
    protected function order(Select $select)
    {
        $sortRules = $this->getModel()->getSortRules();

        if (empty($sortRules)) {
            return $this;
        }

        $default = reset($sortRules);
        $directions = [];
        $columns = explode(',', $default);
        foreach ($columns as $spec) {
            $columnAndDirection = explode(' ', trim($spec), 2);
            $column = array_shift($columnAndDirection);
            if (! empty($columnAndDirection)) {
                $direction = $columnAndDirection[0];
            } else {
                $direction = null;
            }
            $directions[$column] = $direction;
        }

        $order = [];
        $resolver = $this->getResolver();

        foreach ($resolver->requireAndResolveColumns($this, array_keys($directions)) as list($model, $alias, $column)) {
            $direction = reset($directions);
            $selectColumns = $resolver->getSelectColumns($model);
            $tableName = $resolver->getAlias($model);

            if (isset($selectColumns[$column])) {
                $column = $selectColumns[$column];
            }

            $order[] = implode(
                ' ',
                array_filter([$resolver->qualifyColumn($column, $tableName), $direction])
            );

            array_unshift($directions);
        }

        $select->orderBy($order);

        return $this;
    }
}
