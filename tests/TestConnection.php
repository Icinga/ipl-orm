<?php

namespace ipl\Tests\Orm;

use ipl\Sql\Connection;

/**
 * Config-less test connection
 */
class TestConnection extends Connection
{
    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct()
    {
        $this->adapter = new TestAdapter();
    }
}
