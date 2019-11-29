<?php

namespace ipl\Orm;

use ArrayObject;
use Generator;
use InvalidArgumentException;
use ipl\Sql\Connection;
use ipl\Sql\LimitOffset;
use ipl\Sql\LimitOffsetInterface;
use ipl\Sql\Select;
use ipl\Stdlib\Contract\PaginationInterface;
use IteratorAggregate;
use SplObjectStorage;

/**
 * Represents a database query which is associated to a model and a database connection.
 */
class Query implements LimitOffsetInterface, PaginationInterface, IteratorAggregate
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

    /** @var Resolver Column and relation resolver */
    protected $resolver;

    /** @var Select Base SELECT query */
    protected $selectBase;

    /** @var Relation[] Relations to eager load */
    protected $with = [];

    /** @var Relation[] Relations to utilize (join) */
    protected $utilize = [];

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
        $this->getResolver()->setAlias($model, $model->getTableName());

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
     * Get the query's resolver
     *
     * @return Resolver
     */
    public function getResolver()
    {
        if ($this->resolver === null) {
            $this->resolver = (new Resolver())
                ->setQuery($this);
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

            $this->selectBase->from([
                $this->getResolver()->getAlias($this->getModel()) => $this->getModel()->getTableName()
            ]);
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
        $tableName = $this->getModel()->getTableName();
        foreach ((array) $relations as $relation) {
            $relation = $this->getResolver()->qualifyPath($relation, $tableName);
            $this->with[$relation] = $this->getResolver()->resolveRelation($relation);
        }

        return $this;
    }

    /**
     * Remove an eager loaded relation
     *
     * @param string|array $relations
     *
     * @return $this
     */
    public function without($relations)
    {
        $tableName = $this->getModel()->getTableName();
        foreach ((array) $relations as $relation) {
            $relation = $this->getResolver()->qualifyPath($relation, $tableName);
            unset($this->with[$relation]);
        }

        return $this;
    }

    /**
     * Get utilized (joined) relations
     *
     * @return Relation[]
     */
    public function getUtilize()
    {
        return $this->utilize;
    }

    /**
     * Add a relation to utilize (join)
     *
     * @param string $path
     *
     * @return $this
     */
    public function utilize($path)
    {
        $path = $this->getResolver()->qualifyPath($path, $this->getModel()->getTableName());
        $this->utilize[$path] = $this->getResolver()->resolveRelation($path);

        return $this;
    }

    /**
     * Remove a utilized (joined) relation
     *
     * @param string $path
     *
     * @return $this
     */
    public function omit($path)
    {
        $path = $this->getResolver()->qualifyPath($path, $this->getModel()->getTableName());
        unset($this->utilize[$path]);

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
            $resolved = $this->groupColumnsByTarget($resolver->requireAndResolveColumns($columns));

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

            foreach ($this->getWith() as $relation) {
                $select->columns(
                    $resolver->qualifyColumnsAndAliases(
                        $resolver->getSelectColumns($relation->getTarget()),
                        $resolver->getAlias($relation->getTarget())
                    )
                );
            }
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

        $joinedRelations = [];
        foreach ($this->getWith() + $this->getUtilize() as $path => $_) {
            foreach ($resolver->resolveRelations($path) as $relationPath => $relation) {
                if (isset($joinedRelations[$relationPath])) {
                    continue;
                }

                foreach ($relation->resolve() as list($source, $target, $relatedKeys)) {
                    /** @var Model $source */
                    /** @var Model $target */

                    $sourceAlias = $resolver->getAlias($source);
                    $targetAlias = $resolver->getAlias($target);

                    $conditions = [];
                    foreach ($relatedKeys as $fk => $ck) {
                        $conditions[] = sprintf(
                            '%s = %s',
                            $resolver->qualifyColumn($fk, $targetAlias),
                            $resolver->qualifyColumn($ck, $sourceAlias)
                        );
                    }

                    $table = [$targetAlias => $target->getTableName()];

                    switch ($relation->getJoinType()) {
                        case 'LEFT':
                            $select->joinLeft($table, $conditions);

                            break;
                        case 'RIGHT':
                            $select->joinRight($table, $conditions);

                            break;
                        case 'INNER':
                        default:
                            $select->join($table, $conditions);
                    }
                }

                $joinedRelations[$relationPath] = true;
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

        foreach ($this->getWith() as $path => $relation) {
            $target = $relation->getTarget();
            $targetColumns = $resolver->getSelectableColumns($target);

            $defaults = [];
            foreach ($this->getResolver()->getRelations($target) as $targetRelation) {
                $name = $targetRelation->getName();
                $isOne = $targetRelation->isOne();

                $defaults[$name] = function (Model $model) use ($name, $isOne) {
                    $query = $this->derive($name, $model);
                    return $isOne ? $query->first() : $query;
                };
            }

            $hydrator->add(
                explode('.', $path, 2)[1],
                $relation->getName(),
                $relation->getTargetClass(),
                array_combine(
                    array_keys($resolver->qualifyColumnsAndAliases(
                        $targetColumns,
                        $resolver->getAlias($relation->getTarget())
                    )),
                    $targetColumns
                ),
                $defaults,
                $this->getResolver()->getBehaviors($target)
            );
        }

        $defaults = [];
        foreach ($this->getResolver()->getRelations($model) as $relation) {
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

        $hydrator->setBehaviors($this->getResolver()->getBehaviors($model));

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
        // TODO: Think of a way to merge derive() and createSubQuery()
        return $this->createSubQuery(
            $this->getResolver()->getRelations($source)->get($relation)->getTarget(),
            $this->getResolver()->qualifyPath($relation, $source->getTableName()),
            $source
        );
    }

    /**
     * Create a sub-query linked to rows of this query
     *
     * @param Model $target The model to query
     * @param string $targetPath The target's absolute relation path
     * @param Model $from The source model
     *
     * @return static
     */
    public function createSubQuery(Model $target, $targetPath, Model $from = null)
    {
        $subQuery = (new static())
            ->setDb($this->getDb())
            ->setModel($target);

        $resolver = $this->getResolver();
        $sourceParts = array_reverse(explode('.', $targetPath));
        $sourceParts[0] = $target->getTableName();

        $subQueryResolver = $subQuery->getResolver();
        $sourcePath = join('.', $sourceParts);
        $subQuery->with($sourcePath); // TODO: Selects the source's columns again, don't do that

        // TODO: Should be done by the caller. Though, that's not possible until we've got a filter abstraction
        //       which allows to post-pone filter column qualification.
        $subQueryResolver->setAliasPrefix('sub_');

        $baseAlias = $resolver->getAlias($this->getModel());
        $sourceAlias = $subQueryResolver->getAlias($subQueryResolver->resolveRelation($sourcePath)->getTarget());

        $subQueryConditions = [];
        foreach ((array) $this->getModel()->getKeyName() as $column) {
            $fk = $subQueryResolver->qualifyColumn($column, $sourceAlias);

            if (isset($from->$column)) {
                $subQueryConditions["$fk = ?"] = $from->$column;
            } else {
                $subQueryConditions[] = "$fk = " . $resolver->qualifyColumn($column, $baseAlias);
            }
        }

        $subQuery->getSelectBase()->where($subQueryConditions);

        return $subQuery;
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

        foreach ($resolver->requireAndResolveColumns(array_keys($directions)) as list($model, $alias, $column)) {
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
