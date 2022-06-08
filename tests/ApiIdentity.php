<?php

namespace ipl\Tests\Orm;

use ipl\Orm\AliasedExpression;
use ipl\Orm\Behaviors;
use ipl\Orm\Contract\RewriteColumnBehavior;
use ipl\Orm\Model;
use ipl\Orm\Relations;
use ipl\Stdlib\Filter\Condition;

class ApiIdentity extends Model
{
    public function getTableName()
    {
        return 'api_identity';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'user_id',
            'api_token'
        ];
    }

    public function createBehaviors(Behaviors $behaviors)
    {
        $rewriteBehavior = new class () implements RewriteColumnBehavior {
            public function rewriteColumn($column, $relation = null)
            {
                if ($column === 'api_token') {
                    $relation = str_replace('.', '_', $relation);
                    return new AliasedExpression("{$relation}_api_token", '"api_token retrieval not permitted"');
                }
            }

            public function rewriteCondition(Condition $condition, $relation = null)
            {
            }
        };

        $behaviors->add($rewriteBehavior);
    }

    public function createRelations(Relations $relations)
    {
        $relations->belongsTo('user', User::class);
    }
}
