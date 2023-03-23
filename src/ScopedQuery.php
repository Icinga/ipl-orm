<?php

namespace ipl\Orm;

use ipl\Sql\Connection;
use ipl\Sql\Delete;
use ipl\Sql\Insert;
use ipl\Sql\Update;

class ScopedQuery
{
    /** @var Connection Database connection */
    protected $conn;

    /** @var Insert|Update|Delete */
    protected $query;

    public function __construct(Connection $conn, $query)
    {
        $this->conn = $conn;
        $this->query = $query;
    }

    /**
     * Get the underlying base query
     *
     * @return Delete|Insert|Update
     */
    public function getBaseQuery()
    {
        return $this->query;
    }

    /**
     * Get database connection
     *
     * @return Connection
     */
    public function getConn(): Connection
    {
        return $this->conn;
    }

    /**
     * Run the underlying query
     *
     * @return \PDOStatement
     */
    public function execute()
    {
        return $this->getConn()->prepexec($this->query);
    }

    /**
     * Dump the underlying assembled query
     *
     * @return array
     */
    public function dump()
    {
        return $this->getConn()->getQueryBuilder()->assemble($this->query);
    }
}
