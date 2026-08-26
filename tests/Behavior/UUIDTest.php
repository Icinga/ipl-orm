<?php

namespace ipl\Tests\Orm\Behavior;

use ipl\Orm\Behavior\UUID;
use ipl\Orm\Exception\ValueConversionException;
use ipl\Orm\Query;
use ipl\Sql\Connection;
use ipl\Tests\Orm\TestConnection;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid as RamseyUuid;
use UnexpectedValueException;

class UUIDTest extends TestCase
{
    protected const TEST_COLUMN = 'column';

    protected const TEST_UUID_VALUE = '020ed4e8-0fb3-4a98-8235-4aa2341cf1f9';

    public function testRetrievePropertyReturnsNullWhenValueIsNull(): void
    {
        $this->assertNull($this->behavior()->retrieveProperty(null, static::TEST_COLUMN));
        $this->assertNull($this->behavior(true)->retrieveProperty(null, static::TEST_COLUMN));
    }

    public function testRetrievePropertyReturnsUuidInstance(): void
    {
        $this->assertSame(
            static::TEST_UUID_VALUE,
            (string) $this->behavior()->retrieveProperty(
                RamseyUuid::fromString(static::TEST_UUID_VALUE)->getBytes(),
                static::TEST_COLUMN,
            )
        );
        $this->assertSame(
            static::TEST_UUID_VALUE,
            (string) $this->behavior(true)->retrieveProperty(static::TEST_UUID_VALUE, static::TEST_COLUMN)
        );
    }

    public function testPersistPropertyReturnsNullWhenValueIsNull(): void
    {
        $this->assertNull($this->behavior()->persistProperty(null, static::TEST_COLUMN));
        $this->assertNull($this->behavior(true)->persistProperty(null, static::TEST_COLUMN));
    }

    public function testPersistPropertyReturnsValidValueFromString(): void
    {
        $uuid = RamseyUuid::fromString(static::TEST_UUID_VALUE);
        $this->assertSame(
            $uuid->getBytes(),
            $this->behavior()->persistProperty(static::TEST_UUID_VALUE, static::TEST_COLUMN)
        );
        $this->assertSame(
            static::TEST_UUID_VALUE,
            $this->behavior(true)->persistProperty(static::TEST_UUID_VALUE, static::TEST_COLUMN)
        );
    }

    public function testPersistPropertyReturnsValidValueFromUuid(): void
    {
        $uuid = RamseyUuid::fromString(static::TEST_UUID_VALUE);
        $this->assertSame(
            $uuid->getBytes(),
            $this->behavior()->persistProperty($uuid, static::TEST_COLUMN)
        );
        $this->assertSame(
            static::TEST_UUID_VALUE,
            $this->behavior(true)->persistProperty($uuid, static::TEST_COLUMN),
        );
    }

    public function testRetrievePropertyThrowsWithInvalidValueType(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->behavior()->retrieveProperty(true, static::TEST_COLUMN);
        $this->behavior(true)->retrieveProperty(false, static::TEST_COLUMN);
    }

    public function testPersistPropertyThrowsWithInvalidValueType(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->behavior()->persistProperty(true, static::TEST_COLUMN);
        $this->behavior(true)->persistProperty(false, static::TEST_COLUMN);
    }

    public function testRetrievePropertyThrowsWithInvalidUuid(): void
    {
        $this->expectException(ValueConversionException::class);
        $this->behavior()->retrieveProperty('something', static::TEST_COLUMN);
        $this->behavior(true)->retrieveProperty('something', static::TEST_COLUMN);
    }

    public function testPersistPropertyThrowsWithInvalidUuid(): void
    {
        $this->expectException(ValueConversionException::class);
        $this->behavior()->persistProperty('something', static::TEST_COLUMN);
        $this->behavior(true)->persistProperty('something', static::TEST_COLUMN);
    }

    protected function behavior(bool $postgres = false): UUID
    {
        return (new UUID([static::TEST_COLUMN]))
            ->setQuery(
                (new Query())
                    ->setDb($postgres ? new Connection(['db' => 'pgsql']) : new TestConnection())
            );
    }
}
