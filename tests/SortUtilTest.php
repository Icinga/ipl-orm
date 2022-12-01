<?php

namespace ipl\Tests\Orm;

use InvalidArgumentException;
use ipl\Orm\Common\SortUtil;
use PHPUnit\Framework\TestCase;

class SortUtilTest extends TestCase
{
    public function testCreateOrderByWorksFine()
    {
        $this->assertSame(
            [['foo', 'asc'], ['bar', 'desc']],
            SortUtil::createOrderBy('foo asc, bar desc')
        );

        $this->assertSame(
            [['foo', null], ['bar', 'desc']],
            SortUtil::createOrderBy('foo, bar desc')
        );

        $this->assertSame(
            [['foo', 'asc'], ['bar', 'desc']],
            SortUtil::createOrderBy(['foo asc', 'bar desc'])
        );

        $this->assertSame(
            [['foo', null], ['bar', 'desc']],
            SortUtil::createOrderBy(['foo', 'bar desc'])
        );
    }

    public function testCreateOrderByComplainsAboutInvalidDirections()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sort direction "bar"');

        SortUtil::createOrderBy('foo bar');
    }

    public function testExplodeSortSpecWorksFine()
    {
        $this->assertSame(
            ['foo asc', 'bar desc'],
            SortUtil::explodeSortSpec('foo asc, bar desc')
        );

        $this->assertSame(
            ['foo', 'bar desc'],
            SortUtil::explodeSortSpec('foo, bar desc')
        );

        $this->assertSame(
            ['foo asc', 'bar desc'],
            SortUtil::explodeSortSpec(['foo asc', 'bar desc'])
        );

        $this->assertSame(
            ['foo', 'bar desc'],
            SortUtil::explodeSortSpec(['foo', 'bar desc'])
        );
    }

    public function testNormalizeSortSpecWorksFine()
    {
        $this->assertSame(
            'foo asc,bar desc',
            SortUtil::normalizeSortSpec(['foo asc', 'bar desc'])
        );

        $this->assertSame(
            'foo,bar desc',
            SortUtil::normalizeSortSpec(['foo', 'bar desc'])
        );

        $this->assertSame(
            'foo asc,bar desc',
            SortUtil::normalizeSortSpec('foo asc, bar desc')
        );

        $this->assertSame(
            'foo,bar desc',
            SortUtil::normalizeSortSpec('foo, bar desc')
        );
    }

    public function testSplitColumnAndDirectionWorksFine()
    {
        $this->assertSame(
            ['foo', 'asc'],
            SortUtil::splitColumnAndDirection('foo asc')
        );

        $this->assertSame(
            ['foo', null],
            SortUtil::splitColumnAndDirection('foo')
        );
    }
}
