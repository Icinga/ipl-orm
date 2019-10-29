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
use OutOfBoundsException;
use SplObjectStorage;
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
            $this->selectBase = (new Select())
                ->from($this->getModel()->getTableName());
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
        $tableName = $model->getTableName();
        $select = clone $this->getSelectBase();
        $resolver = $this->getResolver();

        if (! empty($columns)) {
            list($modelColumns, $foreignColumnMap) = $resolver->requireAndResolveColumns($this, $columns);

            $select->columns($resolver->qualifyColumns($modelColumns, $resolver->getAlias($model)));

            foreach ($foreignColumnMap as $relation => $foreignColumns) {
                $select->columns(
                    $resolver->qualifyColumnsAndAliases(
                        $foreignColumns,
                        $resolver->getAlias($this->with[$resolver->qualifyPath($relation, $tableName)]->getTarget())
                    )
                );
            }
        } else {
            $select->columns(
                $resolver->qualifyColumns($resolver->getSelectColumns($model), $resolver->getAlias($model))
            );
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
                    return $isOne ? $query->first() : $query->execute();
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
}
