<?php

namespace ipl\Orm\Behavior;

use ipl\Orm\Contract\QueryAwareBehavior;
use ipl\Orm\Contract\RewriteFilterBehavior;
use ipl\Orm\Query;
use ipl\Sql\Adapter\Pgsql;
use ipl\Stdlib\Filter;

class NonTextMatch implements RewriteFilterBehavior, QueryAwareBehavior
{
    /** @var Query */
    protected $query;

    public function setQuery(Query $query)
    {
        if ($query->getDb()->getAdapter() instanceof Pgsql) {
            $this->query = $query;
        }

        return $this;
    }

    public function rewriteCondition(Filter\Condition $condition, $relation = null)
    {
        if ($this->query === null || (! $condition instanceof Filter\Like && ! $condition instanceof Filter\Unlike)) {
            return null;
        }

        $columnDefinition = $this->query->getResolver()->getColumnDefinition($condition->metaData()->get('columnPath'));
        if ($columnDefinition->getType() !== 'text') {
            $condition->setColumn($condition->getColumn() . '::text');
        }
    }
}
