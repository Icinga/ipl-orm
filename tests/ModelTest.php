<?php

namespace ipl\Tests\Orm;

use ipl\Orm\Model;

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

    public function testModelsCanBeInitializedWithProperties(): void
    {
        $model = new class (['foo' => 'bar']) extends Model {
            public function getTableName()
            {
                return 'test';
            }

            public function getKeyName()
            {
                return 'id';
            }

            public function getColumns()
            {
                return ['foo'];
            }
        };

        $this->assertSame('bar', $model->foo);
    }

    public function testModelsCanBeInitializedWithoutProperties(): void
    {
        $model = new class extends Model {
            public function getTableName()
            {
                return 'test';
            }

            public function getKeyName()
            {
                return 'id';
            }

            public function getColumns()
            {
                return ['foo'];
            }
        };

        $this->assertFalse(isset($model->foo));
    }
}
