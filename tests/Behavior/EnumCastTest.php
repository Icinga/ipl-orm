<?php

namespace ipl\Tests\Orm;

use InvalidArgumentException;
use ipl\Orm\Behavior\EnumCast;
use ipl\Orm\Exception\ValueConversionException;
use stdClass;
use PHPUnit\Framework\TestCase;
use TypeError;

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
enum Color: string
{
    case Red   = 'red';
    case Green = 'green';
    case Blue  = 'blue';
}

enum Priority: int
{
    case Low  = 1;
    case High = 2;
}

class EnumCastTest extends TestCase
{
    private EnumCast $behavior;

    protected function setUp(): void
    {
        $this->behavior = new EnumCast(Color::class, ['color']);
    }

    public function testConstructorThrowsForNonBackedEnumTypes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EnumCast(stdClass::class, ['col']);
    }

    public function testFromDbConvertsScalarToEnumCase(): void
    {
        $this->assertSame(Color::Red, $this->behavior->fromDb('red', 'color', null));
    }

    public function testFromDbPreservesNull(): void
    {
        $this->assertNull($this->behavior->fromDb(null, 'color', null));
    }

    public function testToDbUnwrapsEnumCaseToBackingValue(): void
    {
        $this->assertSame('red', $this->behavior->toDb(Color::Red, 'color', null));
    }

    public function testToDbPassesThroughValidBackingValue(): void
    {
        $this->assertSame('red', $this->behavior->toDb('red', 'color', null));
    }

    public function testToDbThrowsForInvalidBackingValue(): void
    {
        $this->expectException(ValueConversionException::class);

        $this->behavior->toDb('invalid', 'color', null);
    }

    public function testToDbThrowsForIncompatibleEnumInstance(): void
    {
        $this->expectException(TypeError::class);
        $this->behavior->toDb(Priority::Low, 'color', null);
    }

    public function testToDbPassesThroughNull(): void
    {
        $this->assertNull($this->behavior->toDb(null, 'color', null));
    }

    public function testFromDbThrowsForUnknownBackingValue(): void
    {
        $this->expectException(ValueConversionException::class);

        $this->behavior->fromDb('purple', 'color', null);
    }

    public function testFromDbIsIdempotentWhenValueIsAlreadyAnEnumInstance(): void
    {
        $this->assertSame(Color::Red, $this->behavior->fromDb(Color::Red, 'color', null));
    }

    public function testToDbIsIdempotentWhenValueIsAlreadyABackingScalar(): void
    {
        $scalar = $this->behavior->toDb(Color::Red, 'color', null);
        $this->assertSame($scalar, $this->behavior->toDb($scalar, 'color', null));
    }

    public function testFromDbConvertsIntBackedEnumCase(): void
    {
        $behavior = new EnumCast(Priority::class, ['priority']);
        $this->assertSame(Priority::Low, $behavior->fromDb(1, 'priority', null));
    }

    public function testToDbUnwrapsIntBackedEnumCaseToBackingValue(): void
    {
        $behavior = new EnumCast(Priority::class, ['priority']);
        $this->assertSame(1, $behavior->toDb(Priority::Low, 'priority', null));
    }
}
