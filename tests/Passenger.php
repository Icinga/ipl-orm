<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Model;
use ipl\Orm\Relations;

class Passenger extends Model
{
    public function getTableName()
    {
        return 'passenger';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'car_id',
            'name',
            'gender' => 'sex'
        ];
    }

    public function createRelations(Relations $relations)
    {
        $relations->belongsTo('car', Car::class);
    }
}
