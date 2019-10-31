<?php

namespace ipl\Orm\Compat;

use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterChain;
use Icinga\Data\Filter\FilterExpression;
use ipl\Orm\Query;

class FilterProcessor extends \ipl\Sql\Compat\FilterProcessor
{
    public static function apply(Filter $filter, Query $query)
    {
        if (! $filter->isEmpty()) {
            $filter = clone $filter;

            static::requireAndResolveFilterColumns($filter, $query, $filter);

            $where = static::assembleFilter($filter);

            if ($where) {
                $operator = array_shift($where);
                $conditions = array_shift($where);

                $query->getSelectBase()->where($conditions, $operator);
            }
        }
    }

    protected static function requireAndResolveFilterColumns(Filter $filter, Query $query, Filter $root)
    {
        if ($filter instanceof FilterExpression) {
            $expression = $filter->getExpression();
            if ($expression === '*') {
                // Wildcard only filters are ignored so stop early here to avoid joining a table for nothing
                return;
            }

            $baseTable = $query->getModel()->getTableName();
            $column = $filter->getColumn();

            $dot = strrpos($column, '.');
            if ($dot !== false) {
                $relations = explode('.', substr($column, 0, $dot));
                if ($relations[0] !== $baseTable) {
                    // Prepend the base table if missing to ensure we'll deal only with absolute paths next
                    array_unshift($relations, $baseTable);
                    $column = $baseTable . '.' . $column;
                }
            } else {
                $relations = [$baseTable];
                $column = $baseTable . '.' . $column;
            }

            do {
                $relationName = array_shift($relations);
                $current[] = $relationName;
                $path = join('.', $current);
                $columnName = substr($column, strlen($path) + 1);

                if ($path === $baseTable) {
                    $subject = $query->getModel();
                } else {
                    $relation = $query->with($path)
                        ->getWith()[$path];
                    $subject = $relation->getTarget();
                }

                $rewrittenFilter = $query->getBehaviors($subject)
                    ->rewriteCondition((clone $filter)->setColumn($columnName), $path . '.');
                if ($rewrittenFilter !== null) {
                    // TODO: Once we have our new filter implementation, make sure that something like
                    //       $filter->getParent()->replaceById($filter->getId(), $rewrittenFilter) works
                    //       ($root is not required anymore)
                    $root->replaceById($filter->getId(), $rewrittenFilter);
                    static::requireAndResolveFilterColumns($rewrittenFilter, $query, $root);
                    return;
                }
            } while (! empty($relations));

            $expression = $query->getBehaviors($subject)->persistProperty($expression, $columnName);
            if (isset($relation)) {
                $column = $query->getResolver()->getAlias($relation->getTarget()) . '.' . $columnName;
            }

            $filter->setColumn($column);
            $filter->setExpression($expression);
        } else {
            /** @var FilterChain $filter */
            foreach ($filter->filters() as $child) {
                static::requireAndResolveFilterColumns($child, $query, $root);
            }
        }
    }
}
