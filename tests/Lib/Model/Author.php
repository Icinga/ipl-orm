<?php

namespace ipl\Tests\Orm\Lib\Model;

use ipl\Orm\Model;
use ipl\Orm\Relations;

class Author extends Model
{
    public function getTableName()
    {
        return 'author';
    }

    public function getKeyName()
    {
        return 'author_ref';
    }

    public function getColumns()
    {
        return [
            'author_ref',
            'name'
        ];
    }

    public function createRelations(Relations $relations)
    {
        // Intentionally no inverse relation: reversing Book->author must create it eagerly, and there is
        // no junction model to re-derive the keys from, so the reversed keys come solely from reverse().
    }
}
