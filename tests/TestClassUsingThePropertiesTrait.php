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

    public function getFoobarProperty()
    {
        return 'foobar';
    }

    public function setSpecialProperty($value)
    {
        $this->properties['special'] = strtoupper($value);
    }
}
