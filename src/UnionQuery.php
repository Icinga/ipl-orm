<?php

namespace ipl\Orm;

use ipl\Sql\Select;

class UnionQuery extends Query
{
    /** @var array Underlying queries */
    private $unions;

    /**
     * Get the underlying queries
     *
     * @return array
     */
    public function getUnions()
    {
        if ($this->unions === null) {
            $this->unions = [];

            foreach ($this->getModel()->getUnions() as list($target, $columns)) {
                $query = (new Query())
                    ->setDb($this->getDb())
                    ->setModel(new $target())
                    ->columns($columns);

                $this->unions[] = $query;
            }
        }

        return $this->unions;
    }

    public function getSelectBase()
    {
        if ($this->selectBase === null) {
            $this->selectBase = new Select();
        }

        $union = new Select();

        foreach ($this->getUnions() as $query) {
            $union->unionAll($query->assembleSelect());
        }

        $this->selectBase->from([$this->getModel()->getTableName() => $union]);

        return $this->selectBase;
    }
}
