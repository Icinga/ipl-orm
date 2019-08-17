<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Relations;

class TestModelWithCreateRelations extends TestModel
{
    public $relationsCreatedCount = 0;

    public function createRelations(Relations $relations)
    {
        ++$this->relationsCreatedCount;
    }
}
