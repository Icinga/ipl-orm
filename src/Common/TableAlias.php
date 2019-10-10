<?php

namespace ipl\Orm\Common;

trait TableAlias
{
    /** @var string Table alias */
    protected $tableAlias;

    /**
     * Get the table alias
     *
     * @return string
     */
    public function getTableAlias()
    {
        return $this->tableAlias;
    }

    /**
     * Set the table alias
     *
     * @param string $tableAlias
     *
     * @return $this
     */
    public function setTableAlias($tableAlias)
    {
        $this->tableAlias = $tableAlias;

        return $this;
    }
}
