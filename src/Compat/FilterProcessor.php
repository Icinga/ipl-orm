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
            if ($filter->getExpression() === '*') {
                // Wildcard only filters are ignored so stop early here to avoid joining a table for nothing
                return;
            }

            $column = $filter->getColumn();

            $dot = strrpos($column, '.');

            if ($dot !== false) {
                $relation = substr($column, 0, $dot);
                $column = substr($column, $dot + 1);

                if ($relation !== $query->getModel()->getTableName()) {
                    $tableName = $query
                        ->with($relation)
                        ->getWith()[$relation]
                        ->getTableAlias();

                    $filter->setColumn($tableName . '.' . $column);
                } else {
                    $filter->setColumn( $query->getModel()->getTableName() . '.' . $column);
                }
            } else {
                $filter->setColumn( $query->getModel()->getTableName() . '.' . $column);
            }
        } else {
            /** @var FilterChain $filter */
            foreach ($filter->filters() as $child) {
                static::requireAndResolveFilterColumns($child, $query);
            }
        }
    }
}
