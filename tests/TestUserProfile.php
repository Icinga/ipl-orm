<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Relations;

class TestUserProfile extends Profile
{
    public function getTableAlias(): string
    {
        return 'test_user_profile';
    }

    public function createRelations(Relations $relations)
    {
        $relations->belongsTo('test_user', TestUserWithTableAlias::class);
    }
}
