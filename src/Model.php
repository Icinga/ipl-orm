<?php

namespace ipl\Orm;

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
     * Initialize the model
     *
     * If you want to adjust the model after construction, override this method.
     */
    protected function init()
    {
    }
}
