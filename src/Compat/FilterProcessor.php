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

            static::requireAndResolveFilterColumns($filter, $query);

            $where = static::assembleFilter($filter);

            if ($where) {
                $operator = array_shift($where);

                $query->getSelectBase()->where($where, $operator);
            }
        }
    }

    protected static function requireAndResolveFilterColumns(Filter $filter, Query $query)
    {
        if ($filter instanceof FilterExpression) {
            $expression = $filter->getExpression();
            if ($expression === '*') {
                // Wildcard only filters are ignored so stop early here to avoid joining a table for nothing
                return;
            }

            $column = $filter->getColumn();

            $dot = strrpos($column, '.');
            if ($dot !== false) {
                $relationName = substr($column, 0, $dot);
                $column = substr($column, $dot + 1);

                if ($relationName !== $query->getModel()->getTableName()) {
                    $relation = $query->with($relationName)
                        ->getWith()[$relationName];
                    $expression = $query->getBehaviors($relation->getTarget())
                        ->persistProperty($expression, $column);
                    $column = $relation->getTableAlias() . '.' . $column;
                } else {
                    $expression = $query->getBehaviors()->persistProperty($expression, $column);
                    $column = $query->getModel()->getTableName() . '.' . $column;
                }
            } else {
                $expression = $query->getBehaviors()->persistProperty($expression, $column);
                $column = $query->getModel()->getTableName() . '.' . $column;
            }

            $filter->setColumn($column);
            $filter->setExpression($expression);
        } else {
            /** @var FilterChain $filter */
            foreach ($filter->filters() as $child) {
                static::requireAndResolveFilterColumns($child, $query);
            }
        }
    }
}
