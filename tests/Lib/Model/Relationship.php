<?php

namespace ipl\Tests\Orm\Lib\Model;

use ipl\Orm\Model;
use ipl\Orm\Relations;

class Relationship extends Model
{
    public function getTableName()
    {
        return 'relationship';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return ['coupler'];
    }

    public function createRelations(Relations $relations)
    {
        $relations->hasMany('loose', Loose::class)
            ->setForeignKey('coupler')
            ->setCandidateKey('coupler')
            ->setJoinType('LEFT');
    }
}
