<?php

namespace ipl\Orm;

use ipl\Sql\Select;

class UnionQuery extends Query
{
    public function getSelectBase()
    {
        if ($this->selectBase === null) {
            $this->selectBase = new Select();
        }

        $union = new Select();

        foreach ($this->getModel()->getUnions() as list($target, $columns)) {
            $select = (new Query())
                ->setDb($this->getDb())
                ->setModel(new $target())
                ->columns($columns)
                ->assembleSelect();

            $union->unionAll($select);
        }

        $this->selectBase->from([$this->getModel()->getTableName() => $union]);

        return $this->selectBase;
    }
}
