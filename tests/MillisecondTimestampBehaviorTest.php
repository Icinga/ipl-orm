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

    public function testToDbReturnsUtcTimestampWithNonUtcInput()
    {
        $sometime = DateTime::createFromFormat(
            'Y-m-d H:i:s',
            '2022-11-09 15:00:00',
            new DateTimeZone('Europe/Berlin')
        );

        $this->assertEquals(1668002400000, (new MillisecondTimestamp([]))->toDb($sometime, 'key', null));
    }

    public function testToDbReturnsUtcTimestampWithUtcInput()
    {
        $sometime = DateTime::createFromFormat(
            'Y-m-d H:i:s',
            '2022-11-09 14:00:00',
            new DateTimeZone('UTC')
        );

        $this->assertEquals(1668002400000, (new MillisecondTimestamp([]))->toDb($sometime, 'key', null));

        $sometime = 1668002400;

        $this->assertEquals(1668002400000, (new MillisecondTimestamp([]))->toDb($sometime, 'key', null));
    }

    /**
     * @depends testToDbReturnsUtcTimestampWithNonUtcInput
     */
    public function testFromDbReturnsTimezoneAwareDateTime()
    {
        date_default_timezone_set('Europe/Berlin');

        $this->assertEquals('2022-11-09 15:00:00', (new MillisecondTimestamp([]))
            ->fromDb(1668002400000, 'key', null)->format('Y-m-d H:i:s'));
    }

    public function testFromDbReturnsUtcDateTime()
    {
        date_default_timezone_set('UTC');

        $this->assertEquals('2022-11-09 14:00:00', (new MillisecondTimestamp([]))
            ->fromDb(1668002400000, 'key', null)->format('Y-m-d H:i:s'));
    }

    public function testToDbPreservesMilliseconds()
    {
        $sometime = DateTime::createFromFormat(
            'Y-m-d H:i:s.u',
            '2022-11-09 14:00:00.123',
            new DateTimeZone('UTC')
        );

        $this->assertEquals(1668002400123, (new MillisecondTimestamp([]))->toDb($sometime, 'key', null));

        $sometime = 1668002400.123;

        $this->assertEquals(1668002400123, (new MillisecondTimestamp([]))->toDb($sometime, 'key', null));

        $sometime = '2022-11-09 14:00:00.123';

        $this->assertEquals(1668002400123, (new MillisecondTimestamp([]))->toDb($sometime, 'key', null));
    }
}
