<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Relations;
use ipl\Sql\Expression;

class TestModelWithMoreExpressions extends TestModel
{
    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'expr3' => new Expression('%s + %s', ['related.lorem', 'related.ipsum'])
        ];
    }

    public function createRelations(Relations $relations)
    {
        $relations->hasOne('related', TestModelWithColumns::class);
    }
}
