<?php

namespace ipl\Orm\Compat;

use AppendIterator;
use ArrayIterator;
use ipl\Orm\Query;
use ipl\Orm\Relation;
use ipl\Orm\UnionQuery;
use ipl\Sql\Expression;
use ipl\Sql\Filter\Exists;
use ipl\Sql\Filter\NotExists;
use ipl\Stdlib\Filter;

class FilterProcessor extends \ipl\Sql\Compat\FilterProcessor
{
    protected $baseJoins = [];

    protected $madeJoins = [];

    public static function apply(Filter\Rule $filter, Query $query)
    {
        if ($query instanceof UnionQuery) {
            foreach ($query->getUnions() as $union) {
                static::apply($filter, $union);
            }

            return;
        }

        if ($filter instanceof Filter\Condition || ! $filter->isEmpty()) {
            $filter = clone $filter;
            if (! $filter instanceof Filter\Chain) {
                // TODO: Quickfix, there's probably a better solution?
                $filter = Filter::all($filter);
            }

            $processor = new static();
            foreach ($query->getUtilize() as $path => $_) {
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

    protected function requireAndResolveFilterColumns(Filter\Rule $filter, Query $query)
    {
        if ($filter instanceof Filter\Condition) {
            $column = $filter->getColumn();
            if (isset($filter->relationCol)) {
                $column = $filter->relationCol;
            }

            $resolver = $query->getResolver();
            $baseTable = $query->getModel()->getTableName();
            $column = $resolver->qualifyPath($column, $baseTable);

            $filter->columnPath = $column;

            list($relationPath, $columnName) = preg_split('/\.(?=[^.]+$)/', $column);

            $relations = new AppendIterator();
            $relations->append(new ArrayIterator([$baseTable => null]));
            $relations->append($resolver->resolveRelations($relationPath));
            foreach ($relations as $path => $relation) {
                $columnName = substr($column, strlen($path) + 1);

                if ($path === $baseTable) {
                    $subject = $query->getModel();
                } else {
                    /** @var Relation $relation */
                    $subject = $relation->getTarget();
                }

                $subjectBehaviors = $resolver->getBehaviors($subject);

                // Prepare filter as if it were final to allow full control for rewrite filter behaviors
                $filter->setValue($subjectBehaviors->persistProperty($filter->getValue(), $columnName));
                $filter->setColumn($resolver->getAlias($subject) . '.' . $columnName);
                $filter->relationCol = $columnName;
                $filter->relationPath = $path;

                $rewrittenFilter = $subjectBehaviors->rewriteCondition($filter, $path . '.');
                if ($rewrittenFilter !== null) {
                    return $this->requireAndResolveFilterColumns($rewrittenFilter, $query) ?: $rewrittenFilter;
                }
            }

            if ($relationPath !== $baseTable) {
                $query->utilize($relationPath);
                $this->madeJoins[$relationPath][] = $filter;
            }
        } else {
            /** @var Filter\Chain $filter */

            $subQueryFilters = [];
            foreach ($filter as $child) {
                /** @var Filter\Rule $child */
                $rewrittenFilter = $this->requireAndResolveFilterColumns($child, $query);
                if ($rewrittenFilter !== null) {
                    $filter->replace($child, $rewrittenFilter);
                    $child = $rewrittenFilter;
                }

                // We optimize only single expressions
                if (isset($child->relationPath) && $child instanceof Filter\Condition) {
                    $relationPath = $child->relationPath;
                    if (
                        $relationPath !== $query->getModel()->getTableName()
                        && ! isset($query->getWith()[$relationPath])
                    ) {
                        if (! $query->getResolver()->isDistinctRelation($relationPath)) {
                            if (isset($child->original)) {
                                $column = $child->original->columnPath;
                                $child = $child->original;
                            } else {
                                $column = $child->getColumn();
                            }

                            $subQueryFilters[get_class($child)][$column][] = $child;
                        }
                    }
                }
            }

            foreach ($subQueryFilters as $conditionClass => $filterCombinations) {
                foreach ($filterCombinations as $column => $filters) {
                    // The relation path must be the same for all entries
                    $relationPath = $filters[0]->relationPath;

                    // In case the parent query also selects the relation we may not require a subquery.
                    // Otherwise we form a cartesian product and get unwanted results back.
                    $selectedByParent = isset($query->getWith()[$relationPath]);

                    // Though, only single equal comparisons or those chained with an OR may be evaluated on the base
                    if (
                        $selectedByParent && $conditionClass !== Filter\Unequal::class
                        && (count($filters) === 1 || $filter instanceof Filter\Any)
                    ) {
                        continue;
                    }

                    $relation = $query->getResolver()->resolveRelation($relationPath);
                    $subQuery = $query->createSubQuery($relation->getTarget(), $relationPath);
                    $subQuery->columns([new Expression('1')]);

                    if ($conditionClass === Filter\Unequal::class || $filter instanceof Filter\All) {
                        $targetKeys = join(',', array_values(
                            $subQuery->getResolver()->qualifyColumnsAndAliases(
                                (array) $subQuery->getModel()->getKeyName(),
                                $subQuery->getModel(),
                                false
                            )
                        ));

                        if ($conditionClass !== Filter\Unequal::class || $filter instanceof Filter\Any) {
                            // Unequal (!=) comparisons chained with an OR are considered an XOR
                            $count = count(array_unique(array_map(function (Filter\Condition $f) {
                                return $f->getValue();
                            }, $filters)));
                        } else {
                            // Unequal (!=) comparisons are transformed to equal (=) ones. If chained with an AND
                            // we just have to check for a single result as an object must not match any of these
                            // comparisons
                            $count = 1;
                        }

                        $subQuery->getSelectBase()->having(["COUNT(DISTINCT $targetKeys) >= ?" => $count]);
                    }

                    foreach ($filters as $i => $child) {
                        if ($conditionClass === Filter\Unequal::class) {
                            // Unequal comparisons must be negated since the sub-query is an inverse of the outer one
                            if ($child instanceof Filter\Condition) {
                                $negation = Filter::equal($child->getColumn(), $child->getValue());
                                $negation->relationCol = $child->relationCol;
                                $negation->columnPath = $child->columnPath;
                                $negation->relationPath = $child->relationPath;
                                $filters[$i] = $negation;
                            } else {
                                $filters[$i] = Filter::none($child);
                            }
                        }

                        // Remove joins solely used for filter conditions
                        foreach ($this->madeJoins as $joinPath => &$madeBy) {
                            $madeBy = array_filter($madeBy, function ($relationFilter) use ($child) {
                                return $child !== $relationFilter
                                    && ($child instanceof Filter\Condition || ! $child->has($relationFilter));
                            });

                            if (empty($madeBy)) {
                                if (! isset($this->baseJoins[$joinPath])) {
                                    $query->omit($joinPath);
                                }

                                unset($this->madeJoins[$joinPath]);
                            }
                        }

                        $filter->remove($child);
                    }

                    static::apply(Filter::any(...$filters), $subQuery);

                    if ($conditionClass === Filter\Unequal::class) {
                        $filter->add(new NotExists($subQuery->assembleSelect()->resetOrderBy()));
                    } else {
                        $filter->add(new Exists($subQuery->assembleSelect()->resetOrderBy()));
                    }
                }
            }
        }
    }
}
