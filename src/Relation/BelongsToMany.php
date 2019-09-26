<?php

namespace ipl\Orm\Relation;

use ipl\Orm\Model;
use ipl\Orm\Relation;
use ipl\Orm\Relations;
use function ipl\Stdlib\get_php_type;

/**
 * Many-to-many relationship
 */
class BelongsToMany extends Relation
{
    /** @var string Name of the join table or junction model class */
    protected $through;

    /** @var string|array Column name(s) of the target model's foreign key found in the join table */
    protected $targetForeignKey;

    /** @var string|array Candidate key column name(s) in the target table which references the target foreign key */
    protected $targetCandidateKey;

    /**
     * Get the name of the join table or junction model class
     *
     * @return string
     */
    public function getThrough()
    {
        return $this->through;
    }

    /**
     * Set the join table name or junction model class
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
        $possibleCandidateKey = [$this->getCandidateKey()];
        $possibleForeignKey = [$this->getForeignKey()];

        $target = $this->getTarget();

        $possibleTargetCandidateKey = [$this->getTargetForeignKey() ?: static::getDefaultForeignKey($target)];
        $possibleTargetForeignKey = [$this->getTargetCandidateKey() ?: static::getDefaultCandidateKey($target)];

        $through = $this->getThrough();

        if (class_exists($through)) {
            $junction = new $through();

            if (! $junction instanceof Model) {
                throw new \InvalidArgumentException(sprintf(
                    'Junction model class must be an instance of %s, %s given',
                    Model::class,
                    get_php_type($junction)
                ));
            }

            $relations = new Relations();
            $junction->createRelations($relations);

            if ($relations->has($source->getTableName())) {
                $sourceRelation = $relations->get($source->getTableName());

                $possibleCandidateKey[] = $sourceRelation->getForeignKey();
                $possibleForeignKey[] = $sourceRelation->getCandidateKey();
            }

            if ($relations->has($target->getTableName())) {
                $targetRelation = $relations->get($target->getTableName());

                $possibleTargetCandidateKey[] = $targetRelation->getForeignKey();
                $possibleTargetForeignKey[] = $targetRelation->getCandidateKey();
            }
        } else {
            $junction = (new Junction())
                ->setTableName($through);
        }

        $toJunction = (new HasMany())
            ->setName($junction->getTableName())
            ->setTarget($junction)
            ->setCandidateKey($this->extractKey($possibleCandidateKey))
            ->setForeignKey($this->extractKey($possibleForeignKey));

        $toTarget = (new HasMany())
            ->setName($this->getName())
            ->setTarget($target)
            ->setCandidateKey($this->extractKey($possibleTargetCandidateKey))
            ->setForeignKey($this->extractKey($possibleTargetForeignKey));


        yield from $toJunction->resolve($source);
        yield from $toTarget->resolve($junction);
    }

    protected function extractKey(array $possibleKey)
    {
        $filtered = array_filter($possibleKey);

        return array_pop($filtered);
    }
}
