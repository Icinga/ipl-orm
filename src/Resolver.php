<?php

namespace ipl\Orm;

use ipl\Sql\Expression;
use RuntimeException;
use SplObjectStorage;

/**
 * Column and relation resolver. Acts as glue between queries and models
 */
class Resolver
{
    /** @var SplObjectStorage Selectable columns from resolved models */
    protected $selectableColumns;

    /** @var SplObjectStorage Select columns from resolved models */
    protected $selectColumns;

    /**
     * Create a new resolver
     */
    public function __construct()
    {
        $this->selectableColumns = new SplObjectStorage();
        $this->selectColumns = new SplObjectStorage();
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
            if (is_int($alias)) {
                $alias = $tableName . '_' . $column;
                $column = $tableName . '.' . $column;
            } elseif (! $column instanceof Expression) {
                $column = $tableName . '.' . $column;
            }

            $qualified[$alias] = $column;
        }

        return $qualified;
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
     * @throws RuntimeException If a column does not exist
     */
    public function requireAndResolveColumns(Query $query, array $columns)
    {
        $model = $query->getModel();
        $tableName = $model->getTableName();
        $modelColumns = [];
        $foreignColumnMap = [];

        foreach ($columns as $alias => $column) {
            if (! $column instanceof Expression) {
                $dot = strrpos($column, '.');

                switch (true) {
                    /** @noinspection PhpMissingBreakStatementInspection */
                    case $dot !== false:
                        $relation = substr($column, 0, $dot);
                        $column = substr($column, $dot + 1);

                        if ($relation !== $tableName) {
                            $query->with($relation);

                            $target = $query->getWith()[$relation]->getTarget();

                            $resolved = &$foreignColumnMap[$relation];

                            break;
                        }
                    // Move to default
                    default:
                        $target = $model;

                        $resolved = &$modelColumns;
                }
            }

            if (is_int($alias)) {
                $resolved[] = $column;
            } else {
                $resolved[$alias] = $column;
            }

            if ($column === '*') {
                continue;
            }

            if ($column instanceof Expression) {
                continue;
            }

            if (! $this->hasSelectableColumn($target, $column)) {
                throw new RuntimeException(sprintf(
                    "Can't require column '%s' in model '%s'. Column not found.",
                    $column,
                    get_class($target)
                ));
            }
        }

        return [$modelColumns, $foreignColumnMap];
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
