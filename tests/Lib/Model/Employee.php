<?php

namespace ipl\Tests\Orm\Lib\Model;

use ipl\Orm\Model;
use ipl\Orm\Relations;
use ipl\Stdlib\Filter;

class Employee extends Model
{
    public function getTableName()
    {
        return 'employee';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'name',
            'active',
            'deleted',
            'role',
            'department_id',
            'office_id'
        ];
    }

    public function createRelations(Relations $relations)
    {
        $relations->belongsTo('department', Department::class);
        $relations->belongsTo('office', Office::class)
            ->setFilter(Filter::equal('city', 'Nuremberg'))
            ->setJoinType('LEFT');
        $relations->hasMany('chair', Chair::class);
        $relations->hasMany('ticket', Ticket::class)
            ->setFilter(Filter::equal('open', 'y'));
    }

    public function createVisibilityFilter(Filter\Chain $filter): void
    {
        $filter->add(Filter::equal('deleted', 'n'));
    }
}
