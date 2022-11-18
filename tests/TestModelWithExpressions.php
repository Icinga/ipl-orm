<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Relations;
use ipl\Sql\Expression;

class TestModelWithExpressions extends TestModel
{
    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'uppercase_text',
            'expr1' => new Expression('LOWER(%s)', ['uppercase_text']), // Base table reference
            'expr2' => new Expression('%s + %s', ['relation.lorem', 'relation.ipsum']) // relation references
        ];
    }

    public function createRelations(Relations $relations)
    {
        $relations->belongsTo('more', TestModelWithMoreExpressions::class);
        $relations->hasOne('relation', TestModelWithColumns::class);
    }
}
