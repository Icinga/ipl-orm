<?php

namespace ipl\Tests\Orm\Lib\Model;

use ipl\Orm\Model;
use ipl\Orm\Relations;

class Loose extends Model
{
    public function getTableName()
    {
        return 'loose';
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
        $relations->hasMany('relationship', Relationship::class)
            ->setForeignKey('coupler')
            ->setCandidateKey('coupler')
            ->setJoinType('LEFT');
    }
}
