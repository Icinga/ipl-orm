<?php

namespace ipl\Tests\Orm\Lib\Model;

use ipl\Orm\Model;
use ipl\Orm\Relations;
use ipl\Stdlib\Filter;

class Department extends Model
{
    public function getTableName()
    {
        return 'department';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'name'
        ];
    }

    public function createRelations(Relations $relations)
    {
        $relations->hasMany('employee', Employee::class)
            ->setFilter(Filter::equal('active', 'y'))
            ->setJoinType('LEFT');
        // Relation filter referencing the target (default) and the source table alias
        $relations->hasMany('lead', Employee::class)
            ->setFilter(Filter::all(
                Filter::equal('role', 'lead'),
                Filter::equal('department.name', 'Engineering')
            ));
    }
}
