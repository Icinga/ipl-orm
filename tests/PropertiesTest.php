<?php

namespace ipl\Tests\Orm;

use OutOfBoundsException;

class PropertiesTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @expectedException OutOfBoundsException
     */
    public function testGetPropertyThrowsOutOfBoundsExceptionIfUnset()
    {
        $subject = new TestClassUsingThePropertiesTrait();

        $subject->foo;
    }

    /**
     * @expectedException OutOfBoundsException
     */
    public function testArrayAccessThrowsOutOfBoundsExceptionIfUnset()
    {
        $subject = new TestClassUsingThePropertiesTrait();

        $subject['foo'];
    }

    public function testGetPropertyReturnsCorrectValueIfSet()
    {
        $subject = new TestClassUsingThePropertiesTrait();
        $subject->foo = 'bar';

        $this->assertSame('bar', $subject->foo);
    }

    public function testArrayAccessReturnsCorrectValueIfSet()
    {
        $subject = new TestClassUsingThePropertiesTrait();
        $subject['foo'] = 'bar';

        $this->assertSame('bar', $subject['foo']);
    }

    public function testIssetReturnsFalseForPropertyAccessIfUnset()
    {
        $subject = new TestClassUsingThePropertiesTrait();

        $this->assertFalse(isset($subject->foo));
    }

    public function testIssetReturnsFalseForArrayAccessIfUnset()
    {
        $subject = new TestClassUsingThePropertiesTrait();

        $this->assertFalse(isset($subject['foo']));
    }

    public function testIssetReturnsTrueForPropertyAccessIfSet()
    {
        $subject = new TestClassUsingThePropertiesTrait();
        $subject->foo = 'bar';

        $this->assertTrue(isset($subject->foo));
    }

    public function testIssetReturnsTrueForArrayAccessIfSet()
    {
        $subject = new TestClassUsingThePropertiesTrait();
        $subject->foo = 'bar';

        $this->assertTrue(isset($subject['foo']));
    }

    public function testUnsetForArrayAccess()
    {
        $subject = new TestClassUsingThePropertiesTrait();
        $subject['foo'] = 'bar';

        $this->assertSame('bar', $subject['foo']);

        unset($subject['foo']);

        $this->expectException(OutOfBoundsException::class);
        $subject['foo'];
    }

    public function testUnsetForPropertyAccess()
    {
        $subject = new TestClassUsingThePropertiesTrait();
        $subject->foo = 'bar';

        $this->assertSame('bar', $subject->foo);

        unset($subject->foo);

        $this->expectException(OutOfBoundsException::class);
        $subject->foo;
    }

    public function testGetMutatorGetsCalled()
    {
        $subject = new TestClassUsingThePropertiesTrait();

        $this->assertSame('foobar', $subject->foobar);
    }

    public function testSetMutatorGetsCalled()
    {
        $subject = new TestClassUsingThePropertiesTrait();
        $subject->special = 'foobar';

        $this->assertSame('FOOBAR', $subject->special);
    }

    public function testGetPropertiesReturnsEmptyArrayIfUnset()
    {
        $this->markTestSkipped('Properties::getProperties() not yet implemented');

        $subject = new TestClassUsingThePropertiesTrait();

        $this->assertSame([], $subject->getProperties());
    }

    public function testGetPropertiesReturnsCorrectValueIfSet()
    {
        $this->markTestSkipped('Properties::getProperties() not yet implemented');

        $subject = (new TestClassUsingThePropertiesTrait())
            ->setProperties(['foo' => 'bar', 'baz' => 'qux']);

        $this->assertSame(['foo' => 'bar', 'baz' => 'qux'], $subject->getProperties());
    }
}
