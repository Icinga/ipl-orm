<?php

namespace ipl\Tests\Orm;

use InvalidArgumentException;
use ipl\Orm\Behavior\BoolCast;

class BoolCastTest extends \PHPUnit\Framework\TestCase
{
    public function testSetFalseValue()
    {
        $this->assertSame('false', (new BoolCast([]))->setFalseValue('false')->getFalseValue());
    }

    public function testSetTrueValue()
    {
        $this->assertSame('true', (new BoolCast([]))->setTrueValue('true')->getTrueValue());
    }

    public function testSetStrict()
    {
        $behavior = new BoolCast([]);

        $this->assertFalse($behavior->setStrict(false)->isStrict());
        $this->assertTrue($behavior->setStrict(true)->isStrict());
    }

    public function testFromDbConvertsToBoolean()
    {
        $behavior = new BoolCast([]);

        $this->assertFalse($behavior->fromDb($behavior->getFalseValue(), 'key', 'context'));
        $this->assertTrue($behavior->fromDb($behavior->getTrueValue(), 'key', 'context'));
    }

    public function testToDbConvertsFromBoolean()
    {
        $behavior = new BoolCast([]);

        $this->assertSame($behavior->getFalseValue(), $behavior->toDb(false, 'key', 'context'));
        $this->assertSame($behavior->getTrueValue(), $behavior->toDb(true, 'key', 'context'));
    }

    public function testStrictIsDefault()
    {
        $this->assertTrue((new BoolCast([]))->isStrict());
    }

    public function testFromDbThrowsExceptionInStrictMode()
    {
        $this->expectException(InvalidArgumentException::class);

        (new BoolCast([]))->fromDb('strict', 'key', 'context');
    }

    public function testToDbThrowsExceptionInStrictMode()
    {
        $this->expectException(InvalidArgumentException::class);

        (new BoolCast([]))->toDb('strict', 'key', 'context');
    }

    public function testFromDbPassesNonMatchingValuesWithStrictModeDisabled()
    {
        $this->assertSame('pass', (new BoolCast([]))->setStrict(false)->fromDb('pass', 'key', 'context'));
    }

    public function testToDbPassesNonMatchingValuesWithStrictModeDisabled()
    {
        $this->assertSame('pass', (new BoolCast([]))->setStrict(false)->toDb('pass', 'key', 'context'));
    }
}
