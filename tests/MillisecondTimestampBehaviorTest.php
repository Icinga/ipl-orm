<?php

namespace ipl\Tests\Orm;

use DateTime;
use DateTimeZone;
use ipl\Orm\Behavior\MillisecondTimestamp;
use PHPUnit\Framework\TestCase;

class MillisecondTimestampBehaviorTest extends TestCase
{
    public function testFromDbReturnsNullWhenNullIsPassed()
    {
        $this->assertNull((new MillisecondTimestamp([]))->fromDb(null, 'key', null));
    }
}
