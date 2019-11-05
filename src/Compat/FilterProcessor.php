<?php

namespace ipl\Orm\Compat;

use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterAnd;
use Icinga\Data\Filter\FilterChain;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Data\Filter\FilterOr;
use ipl\Orm\Query;
use ipl\Orm\UnionQuery;

class FilterProcessor extends \ipl\Sql\Compat\FilterProcessor
{
    protected $baseJoins = [];

    protected $madeJoins = [];

    public static function apply(Filter $filter, Query $query)
    {
        if ($query instanceof UnionQuery) {
            foreach ($query->getUnions() as $union) {
                static::apply($filter, $union);
            }

            return;
        }

        if (! $filter->isEmpty()) {
            $filter = clone $filter;
            if (! $filter->isChain()) {
                // TODO: Quickfix, there's probably a better solution?
                $filter = Filter::matchAll($filter);
            }

            $processor = new static();
            foreach ($query->getWith() as $path => $_) {
                $processor->baseJoins[$path] = true;
            }

            $rewrittenFilter = $processor->requireAndResolveFilterColumns($filter, $query);
            if ($rewrittenFilter !== null) {
                $filter = $rewrittenFilter;
            }

            $where = static::assembleFilter($filter);

            if ($where) {
                $operator = array_shift($where);
                $conditions = array_shift($where);

                $query->getSelectBase()->where($conditions, $operator);
            }
        }
    }

    protected function requireAndResolveFilterColumns(Filter $filter, Query $query)
    {
        if ($filter instanceof FilterExpression) {
            $expression = $filter->getExpression();
            if ($expression === '*') {
                // Wildcard only filters are ignored so stop early here to avoid joining a table for nothing
                return;
            }

            $baseTable = $query->getModel()->getTableName();
            $column = $filter->getColumn();
            if (isset($filter->metaData)) {
                $column = $filter->metaData['relationCol'];
            }

            $column = $query->getResolver()->qualifyPath($column, $baseTable);
            $filter->metaData['column'] = $column;

            // TODO: legacy filter columns? (with underscores)
            list($relationPath, $columnName) = preg_split('/\.(?=[^.]+$)/', $column);

            $filter->metaData['relationPath'] = $relationPath;
            $filter->metaData['relationCol'] = $columnName;

            $relations = explode('.', $relationPath);

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
                    if (isset($rewrittenFilter->transferMetaData) || $rewrittenFilter instanceof $filter) {
                        // The filter hasn't changed semantically
                        $rewrittenFilter->metaData['relationPath'] = $path;
                        $rewrittenFilter->metaData['relationCol'] = $columnName;
                        $rewrittenFilter->metaData['original'] = $filter;
                    }

                    if (isset($relation)) {
                        $this->madeJoins[$path][] = $rewrittenFilter;
                    }

                    $this->requireAndResolveFilterColumns($rewrittenFilter, $query);
                    return $rewrittenFilter;
                } elseif (isset($relation)) {
                    $this->madeJoins[$path][] = $filter;
                }
            } while (! empty($relations));

            $expression = $query->getBehaviors($subject)->persistProperty($expression, $columnName);
            $column = $query->getResolver()->qualifyPath($columnName, $query->getResolver()->getAlias(
                isset($relation) ? $relation->getTarget() : $query->getModel()
            ));

            $filter->setColumn($column);
            $filter->setExpression($expression);
        } else {
            /** @var FilterChain $filter */

            $subQueryFilters = [];
            foreach ($filter->filters() as $child) {
                /** @var Filter $child */
                $rewrittenFilter = $this->requireAndResolveFilterColumns($child, $query);
                if ($rewrittenFilter !== null) {
                    $filter->replaceById($child->getId(), $rewrittenFilter);
                    $child = $rewrittenFilter;
                }

                // We optimize only single expressions or chains with explicit meta-data
                if (! isset($child->noOptmization) && ($child instanceof FilterExpression || isset($child->metaData))) {
                    $child->noOptmization = true;

                    $relationPath = $child->metaData['relationPath'];
                    if ($relationPath !== $query->getModel()->getTableName()) {
                        if (! $query->getWith()[$relationPath]->isOne()) {
                            if (isset($child->metaData['original'])) {
                                $column = $child->metaData['original']->metaData['column'];
                                $sign = $child->metaData['original']->getSign();
                            } else {
                                $column = $child->getColumn();
                                $sign = $child->getSign();
                            }

                            $subQueryFilters[$sign][$column][] = $child;
                        }
                    }
                }
            }

            foreach ($subQueryFilters as $sign => $filterCombinations) {
                foreach ($filterCombinations as $column => $filters) {
                    // The relation path must be the same for all entries
                    $subQuery = $query->createSubQuery($filters[0]->metaData['relationPath']);

                    if ($sign === '!=' || $filter instanceof FilterAnd) {
                        $targetKeys = join(',', array_values(
                            $subQuery->getResolver()->qualifyColumns(
                                (array) $subQuery->getModel()->getKeyName(),
                                $subQuery->getResolver()->getAlias($subQuery->getModel())
                            )
                        ));

                        if ($sign !== '!=' || $filter instanceof FilterOr) {
                            // Unequal (!=) comparisons chained with an OR are considered an XOR
                            $count = count($filters);
                        } else {
                            // Unequal (!=) comparisons are transformed to equal (=) ones. If chained with an AND
                            // we just have to check for a single result as an object must not match any of these
                            // comparisons
                            $count = 1;
                        }

                        $subQuery->getSelectBase()->having(["COUNT(DISTINCT $targetKeys) >= ?" => $count]);
                    }

                    foreach ($filters as $i => $child) {
                        $filter->removeId($child->getId());

                        if ($sign === '!=') {
                            // Unequal comparisons must be negated since the sub-query is an inverse of the outer one
                            if ($child->isExpression()) {
                                $filters[$i] = $child->setSign('=');
                                $filters[$i]->metaData = $child->metaData;
                            } else {
                                $filters[$i] = Filter::not($child);
                            }
                        }

                        // Remove joins solely used for filter conditions
                        foreach ($this->madeJoins as $joinPath => $madeBy) {
                            $madeBy = array_filter($madeBy, function ($relationFilter) use ($child) {
                                return $child->getId() !== $relationFilter->getId()
                                    && ! $child->hasId($relationFilter->getId());
                            });

                            if (empty($madeBy)) {
                                if (! isset($this->baseJoins[$joinPath])) {
                                    $query->without($joinPath);
                                }

                                unset($this->madeJoins[$joinPath]);
                            }
                        }
                    }

                    static::apply(Filter::matchAny($filters), $subQuery);

                    $filter->addFilter(new FilterExpression(
                        '',
                        ($sign === '!=' ? 'NOT ' : '') . 'EXISTS',
                        $subQuery->assembleSelect()
                    ));
                }
            }
        }
    }
}
