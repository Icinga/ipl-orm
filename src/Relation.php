<?php

namespace ipl\Orm;

/**
 * Relations represent the connection between models, i.e. the association between rows in one or more tables
 * on the basis of matching key columns. The relationships are defined using candidate key-foreign key constructs.
 */
class Relation
{
    /** @var string Name of the relation */
    protected $name;

    /** @var string|array Column name(s) of the foreign key found in the target table */
    protected $foreignKey;

    /** @var string|array Column name(s) of the candidate key in the source table which references the foreign key */
    protected $candidateKey;

    /**
     * Get the name of the relation
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the name of the relation
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the column name(s) of the foreign key found in the target table
     *
     * @return string|array Array if the foreign key is compound, string otherwise
     */
    public function getForeignKey()
    {
        return $this->foreignKey;
    }

    /**
     * Set the column name(s) of the foreign key found in the target table
     *
     * @param string|array $foreignKey Array if the foreign key is compound, string otherwise
     *
     * @return $this
     */
    public function setForeignKey($foreignKey)
    {
        $this->foreignKey = $foreignKey;

        return $this;
    }

    /**
     * Get the column name(s) of the candidate key in the source table which references the foreign key
     *
     * @return string|array Array if the candidate key is compound, string otherwise
     */
    public function getCandidateKey()
    {
        return $this->candidateKey;
    }

    /**
     * Set the column name(s) of the candidate key in the source table which references the foreign key
     *
     * @param string|array $candidateKey Array if the candidate key is compound, string otherwise
     *
     * @return $this
     */
    public function setCandidateKey($candidateKey)
    {
        $this->candidateKey = $candidateKey;

        return $this;
    }
}
