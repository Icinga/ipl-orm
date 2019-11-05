<?php

namespace ipl\Orm;

use Generator;
use ipl\Sql\Expression;
use OutOfBoundsException;
use RuntimeException;
use SplObjectStorage;

/**
 * Column and relation resolver. Acts as glue between queries and models
 */
class Resolver
{
    /** @var  SplObjectStorage Model aliases */
    protected $aliases;

    /** @var string The alias prefix to use */
    protected $aliasPrefix;

    /** @var SplObjectStorage Selectable columns from resolved models */
    protected $selectableColumns;

    /** @var SplObjectStorage Select columns from resolved models */
    protected $selectColumns;

    /**
     * Create a new resolver
     */
    public function __construct()
    {
        $this->aliases = new SplObjectStorage();
        $this->selectableColumns = new SplObjectStorage();
        $this->selectColumns = new SplObjectStorage();
    }

    /**
     * Get a model alias
     *
     * @param Model $model
     *
     * @return string
     *
     * @throws OutOfBoundsException If no alias exists for the given model
     */
    public function getAlias(Model $model)
    {
        if (! $this->aliases->contains($model)) {
            throw new OutOfBoundsException(sprintf(
                "Can't get alias for model '%s'. Alias does not exist",
                get_class($model)
            ));
        }

        return $this->aliasPrefix . $this->aliases[$model];
    }

    /**
     * Set a model alias
     *
     * @param Model  $model
     * @param string $alias
     *
     * @return $this
     */
    public function setAlias(Model $model, $alias)
    {
        if (isset($this->aliasPrefix) && strpos($alias, $this->aliasPrefix) === 0) {
            $alias = substr($alias, strlen($this->aliasPrefix));
        }

        $this->aliases[$model] = $alias;

        return $this;
    }

    /**
     * Get the alias prefix
     *
     * @return string
     */
    public function getAliasPrefix()
    {
        return $this->aliasPrefix;
    }

    /**
     * Set the alias prefix
     *
     * @param string $alias
     *
     * @return $this
     */
    public function setAliasPrefix($alias)
    {
        $this->aliasPrefix = $alias;

        return $this;
    }

    /**
     * Get whether the specified model provides the given selectable column
     *
     * @param Model  $subject
     * @param string $column
     *
     * @return bool
     */
    public function hasSelectableColumn(Model $subject, $column)
    {
        if (! $this->selectableColumns->contains($subject)) {
            $this->collectColumns($subject);
        }

        $columns = $this->selectableColumns[$subject];

        return isset($columns[$column]);
    }

    /**
     * Get all selectable columns from the given model
     *
     * @param Model $subject
     *
     * @return array
     */
    public function getSelectableColumns(Model $subject)
    {
        if (! $this->selectableColumns->contains($subject)) {
            $this->collectColumns($subject);
        }

        return array_keys($this->selectableColumns[$subject]);
    }

    /**
     * Get all select columns from the given model
     *
     * @param Model $subject
     *
     * @return array Select columns suitable for {@link \ipl\Sql\Select::columns()}
     */
    public function getSelectColumns(Model $subject)
    {
        if (! $this->selectColumns->contains($subject)) {
            $this->collectColumns($subject);
        }

        return $this->selectColumns[$subject];
    }

    /**
     * Qualify the given alias by the specified table name
     *
     * @param string $alias
     * @param string $tableName
     *
     * @return string
     */
    public function qualifyAlias($alias, $tableName)
    {
        return $tableName . '_' . $alias;
    }

    /**
     * Qualify the given column by the specified table name
     *
     * @param string $column
     * @param string $tableName
     *
     * @return string
     */
    public function qualifyColumn($column, $tableName)
    {
        return $tableName . '.' . $column;
    }

    /**
     * Qualify the given columns by the specified table name
     *
     * @param array  $columns
     * @param string $tableName
     *
     * @return array
     */
    public function qualifyColumns(array $columns, $tableName)
    {
        $qualified = [];

        foreach ($columns as $alias => $column) {
            if (is_int($alias) && ! $column instanceof Expression) {
                $column = $this->qualifyColumn($column, $tableName);
            }

            $qualified[$alias] = $column;
        }

        return $qualified;
    }

    /**
     * Qualify the given columns and aliases by the specified table name
     *
     * @param array  $columns
     * @param string $tableName
     *
     * @return array
     */
    public function qualifyColumnsAndAliases(array $columns, $tableName)
    {
        $qualified = [];

        foreach ($columns as $alias => $column) {
            if (is_int($alias)) {
                $alias = $this->qualifyAlias($column, $tableName);
                $column = $this->qualifyColumn($column, $tableName);
            } elseif (! $column instanceof Expression) {
                $column = $this->qualifyColumn($column, $tableName);
            }

            $qualified[$alias] = $column;
        }

        return $qualified;
    }

    /**
     * Qualify the given path by the specified table name
     *
     * @param string $path
     * @param string $tableName
     *
     * @return string
     */
    public function qualifyPath($path, $tableName)
    {
        $segments = explode('.', $path, 2);

        if ($segments[0] !== $tableName) {
            array_unshift($segments, $tableName);
        }

        $path = implode('.', $segments);

        return $path;
    }


    /**
     * Require and resolve columns
     *
     * Related models will be automatically added for eager-loading.
     *
     * @param Query $query
     * @param array $columns
     *
     * @return Generator
     *
     * @throws RuntimeException If a column does not exist
     */
    public function requireAndResolveColumns(Query $query, array $columns)
    {
        $model = $query->getModel();
        $tableName = $model->getTableName();

        foreach ($columns as $alias => $column) {
            if ($column === '*' || $column instanceof Expression) {
                yield [$model, $alias, $column];

                continue;
            }

            $dot = strrpos($column, '.');

            switch (true) {
                /** @noinspection PhpMissingBreakStatementInspection */
                case $dot !== false:
                    $relation = substr($column, 0, $dot);
                    $column = substr($column, $dot + 1);

                    if ($relation !== $tableName) {
                        $query->with($relation);

                        $target = $query->getWith()[$this->qualifyPath($relation, $tableName)]->getTarget();

                        break;
                    }
                // Move to default
                default:
                    $relation = null;
                    $target = $model;
            }

            if (! $this->hasSelectableColumn($target, $column)) {
                throw new RuntimeException(sprintf(
                    "Can't require column '%s' in model '%s'. Column not found.",
                    $column,
                    get_class($target)
                ));
            }

            yield [$target, $alias, $column];
        }
    }

    /**
     * Collect all selectable columns from the given model
     *
     * @param Model $subject
     */
    protected function collectColumns(Model $subject)
    {
        // Don't fail if Model::getColumns() also contains the primary key columns
        $columns = array_merge((array) $subject->getKeyName(), (array) $subject->getColumns());

        $this->selectColumns->attach($subject, $columns);

        $selectable = [];

        foreach ($columns as $alias => $column) {
            if (is_int($alias)) {
                $selectable[$column] = true;
            } else {
                $selectable[$alias] = true;
            }
        }

        $this->selectableColumns->attach($subject, $selectable);
    }
}