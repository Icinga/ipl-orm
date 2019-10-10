<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Properties;

class TestClassUsingThePropertiesTrait implements \ArrayAccess
{
    use Properties;

    public function __construct()
    {
        $this->accessorsAndMutatorsEnabled = true;
    }

    public function mutateFoobarProperty()
    {
        return 'foobar';
    }

    public function mutateSpecialProperty($value)
    {
        return strtoupper($value);
    }
}
