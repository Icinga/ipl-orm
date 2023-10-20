<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Behavior\Binary;
use ipl\Orm\Exception\ValueConversionException;
use ipl\Orm\Query;
use ipl\Sql\Connection;
use ipl\Stdlib\Filter\Equal;
use UnexpectedValueException;

class BinaryTest extends \PHPUnit\Framework\TestCase
{
    protected const TEST_BINARY_VALUE = 'value';

    protected const TEST_HEX_VALUE = '76616c7565';

    protected const TEST_COLUMN = 'column';

    public function testRetrievePropertyReturnsVanillaValueIfAdapterIsNotPostgreSQL(): void
    {
        $this->assertSame(
            static::TEST_BINARY_VALUE,
            $this->behavior()->retrieveProperty(static::TEST_BINARY_VALUE, static::TEST_COLUMN)
        );
    }

    public function testRetrievePropertyReturnsStreamContentsIfAdapterIsPostgreSQL(): void
    {
        $stream = fopen('php://temp', 'r+');
        fputs($stream, static::TEST_BINARY_VALUE);
        rewind($stream);

        $this->assertSame(
            static::TEST_BINARY_VALUE,
            $this->behavior(true)->retrieveProperty($stream, static::TEST_COLUMN)
        );
    }

    public function testRetrievePropertyRewindsAStreamIfAdapterIsPostgreSQL(): void
    {
        $stream = fopen('php://temp', 'r+');
        fputs($stream, static::TEST_BINARY_VALUE);
        rewind($stream);

        $this->assertSame(
            static::TEST_BINARY_VALUE,
            $this->behavior(true)->retrieveProperty($stream, static::TEST_COLUMN)
        );
        $this->assertSame(
            static::TEST_BINARY_VALUE,
            $this->behavior(true)->retrieveProperty($stream, static::TEST_COLUMN)
        );
    }

    public function testPersistPropertyReturnsVanillaValueIfAdapterIsNotPostgreSQL(): void
    {
        $this->assertSame(
            static::TEST_BINARY_VALUE,
            $this->behavior()->persistProperty(static::TEST_BINARY_VALUE, static::TEST_COLUMN)
        );
    }

    public function testPersistPropertyReturnsByteaHexStringIfAdapterIsPostgreSQL(): void
    {
        $this->assertSame(
            sprintf('\\x%s', static::TEST_HEX_VALUE),
            $this->behavior(true)->persistProperty(static::TEST_BINARY_VALUE, static::TEST_COLUMN)
        );
    }

    public function testRewriteConditionTransformsHexStringToBinaryIfAdapterIsNotPostgreSQL(): void
    {
        $c = $this->condition();

        $this->behavior()->rewriteCondition($c);
        $this->assertSame(static::TEST_BINARY_VALUE, $c->getValue());
    }

    public function testRewriteConditionTransformsHexStringToByteaHexStringIfAdapterIsPostgreSQL(): void
    {
        $c = $this->condition();

        $this->behavior(true)->rewriteCondition($c);
        $this->assertSame(
            sprintf('\\x%s', static::TEST_HEX_VALUE),
            $c->getValue()
        );
    }

    /**
     * We expect `retrieveProperty()` to return stream contents when the adapter is `PostgreSQL`.
     * Working with streams in other functions is neither expected nor supported.
     */
    public function testPersistPropertyThrowsExceptionIfAdapterIsPostgreSQLAndValueIsResource(): void
    {
        $this->expectException(ValueConversionException::class);
        $this->behavior(true)->persistProperty(fopen('php://temp', 'r'), static::TEST_COLUMN);
    }

    /**
     * @see testPersistPropertyThrowsExceptionIfAdapterIsPostgreSQLAndValueIsResource()
     */
    public function testRewriteConditionThrowsExceptionIfAdapterIsPostgreSQLAndValueIsResource(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->behavior(true)->rewriteCondition($this->condition(fopen('php://temp', 'r')));
    }

    protected function behavior(bool $postgres = false): Binary
    {
        return (new Binary([static::TEST_COLUMN]))
            ->setQuery(
                (new Query())
                    ->setDb($postgres ? new Connection(['db' => 'pgsql']) : new TestConnection())
            );
    }

    protected function condition($value = self::TEST_HEX_VALUE): Equal
    {
        $c = new Equal(static::TEST_COLUMN, $value);
        $c->metaData()
            ->set('originalValue', $value)
            ->set('columnName', static::TEST_COLUMN);

        return $c;
    }
}
