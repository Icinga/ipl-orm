<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Relations;

class TestUserWithTableAlias extends User
{
    public function getTableAlias(): string
    {
        return 'test_user';
    }

    public function createRelations(Relations $relations)
    {
        $relations->hasOne('test_user_profile', TestUserProfile::class);
    }
}
