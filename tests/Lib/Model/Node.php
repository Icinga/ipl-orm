<?php

namespace ipl\Tests\Orm\Lib\Model;

use ipl\Orm\Model;
use ipl\Orm\Relations;
use ipl\Stdlib\Filter;

class Node extends Model
{
    public function getTableName()
    {
        return 'node';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'name',
            'parent_id',
            'deleted'
        ];
    }

    public function createRelations(Relations $relations)
    {
        $relations->belongsTo('parent', self::class)
            ->setCandidateKey('parent_id')
            ->setJoinType('LEFT');

        $relations->hasMany('child', self::class)
            ->setForeignKey('parent_id')
            ->setFilter(Filter::equal('child.name', 'foo'));
    }

    public function createVisibilityFilter(Filter\Chain $filter): void
    {
        $filter->add(Filter::equal('deleted', 'n'));
    }
}
