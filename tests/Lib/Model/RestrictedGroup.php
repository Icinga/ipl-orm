<?php

namespace ipl\Tests\Orm\Lib\Model;

use ipl\Orm\Model;
use ipl\Orm\Relations;
use ipl\Stdlib\Filter;

class RestrictedGroup extends Model
{
    public function getTableName()
    {
        return 'restricted_group';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'name',
            'deleted'
        ];
    }

    public function createVisibilityFilter(Filter\Chain $filter): void
    {
        $filter->add(Filter::equal('deleted', 'n'));
    }

    public function createRelations(Relations $relations)
    {
    }
}
