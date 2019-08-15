<?php

namespace ipl\Orm;

use ipl\Sql\Connection;

/**
 * Models represent single database tables or parts of it.
 * They are also used to interact with the tables, i.e. in order to query for data.
 */
abstract class Model
{
    public function __construct()
    {
        $this->init();
    }

    /**
     * Get the related database table's name
     *
     * @return string
     */
    abstract public function getTableName();

    /**
     * Get the column name(s) of the primary key
     *
     * @return string|array Array if the primary key is compound, string otherwise
     */
    abstract public function getKeyName();

    /**
     * Get the model's queryable columns
     *
     * @return array
     */
    abstract public function getColumns();

    /**
     * Get a query which is tied to this model and the given database connection
     *
     * @param Connection $db
     *
     * @return Query
     */
    public static function on(Connection $db)
    {
        return (new Query())
            ->setDb($db)
            ->setModel(new static());
    }

    /**
     * Initialize the model
     *
     * If you want to adjust the model after construction, override this method.
     */
    protected function init()
    {
    }
}
