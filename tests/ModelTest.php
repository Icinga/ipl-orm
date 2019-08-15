<?php

namespace ipl\Tests\Orm;

class ModelTest extends \PHPUnit\Framework\TestCase
{
    public function testInitIsCalledAfterConstruction()
    {
        $model = new TestModelWithInit();

        $this->assertTrue($model->propertyInitialized);
    }

    public function testOnReturnsQueryWithModelAndDatabaseConnectionAssociated()
    {
        $db = new TestConnection();

        $query = TestModel::on($db);

        $this->assertSame($db, $query->getDb());
        /** @noinspection PhpParamsInspection */
        $this->assertInstanceOf(TestModel::class, $query->getModel());
    }
}
