<?php

namespace ipl\Orm\Relation;

use ipl\Orm\Model;
use ipl\Orm\Relation;

/**
 * Many-to-many relationship
 */
class BelongsToMany extends Relation
{
    /** @var string Name of the join table */
    protected $through;

    /** @var string|array Column name(s) of the target model's foreign key found in the join table */
    protected $targetForeignKey;

    /** @var string|array Candidate key column name(s) in the target table which references the target foreign key */
    protected $targetCandidateKey;

    /**
     * Get the name of the join table
     *
     * @return string
     */
    public function getThrough()
    {
        return $this->through;
    }

    /**
     * Set the name of the join table
     *
     * @param string $through
     *
     * @return $this
     */
    public function setThrough($through)
    {
        $this->through = $through;

        return $this;
    }

    /**
     * Get the column name(s) of the target model's foreign key found in the join table
     *
     * @return string|array Array if the foreign key is compound, string otherwise
     */
    public function getTargetForeignKey()
    {
        return $this->targetForeignKey;
    }

    /**
     * Set the column name(s) of the target model's foreign key found in the join table
     *
     * @param string|array $targetForeignKey Array if the foreign key is compound, string otherwise
     *
     * @return $this
     */
    public function setTargetForeignKey($targetForeignKey)
    {
        $this->targetForeignKey = $targetForeignKey;

        return $this;
    }

    /**
     * Get the candidate key column name(s) in the target table which references the target foreign key
     *
     * @return string|array Array if the foreign key is compound, string otherwise
     */
    public function getTargetCandidateKey()
    {
        return $this->targetCandidateKey;
    }

    /**
     * Set the candidate key column name(s) in the target table which references the target foreign key
     *
     * @param string|array $targetCandidateKey Array if the foreign key is compound, string otherwise
     *
     * @return $this
     */
    public function setTargetCandidateKey($targetCandidateKey)
    {
        $this->targetCandidateKey = $targetCandidateKey;

        return $this;
    }

    public function resolve(Model $source)
    {
        $through = $this->getThrough();

        $junction = (new Junction())
            ->setTableName($through);

        $toJunction = (new HasMany())
            ->setName($through)
            ->setTarget($junction)
            ->setCandidateKey($this->getCandidateKey())
            ->setForeignKey($this->getForeignKey());

        $target = $this->getTarget();

        $toTarget = (new HasMany())
            ->setName($this->getName())
            ->setTarget($this->getTarget())
            ->setCandidateKey($this->getTargetForeignKey() ?: static::getDefaultForeignKey($target))
            ->setForeignKey($this->getTargetCandidateKey() ?: static::getDefaultCandidateKey($target));

        yield from $toJunction->resolve($source);
        yield from $toTarget->resolve($junction);
    }
}
