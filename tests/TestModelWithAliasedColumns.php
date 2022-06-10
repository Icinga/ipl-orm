<?php

namespace ipl\Tests\Orm;

use ipl\Sql\Expression;

class TestModelWithAliasedColumns extends TestModel
{
    public function getColumns()
    {
        return [
            'lorem' => new Expression('MAX(test.lorem)'),
            'ipsum' => new Expression('MIN(test.ipsum)'),
            'dolor' => 'sit'
        ];
    }
}
