<?php

namespace ipl\Orm\Relation;

use ipl\Orm\Model;
use ipl\Orm\Relation;
use ipl\Orm\Relations;
use LogicException;

use function ipl\Stdlib\get_php_type;

/**
 * Many-to-many relationship
 */
class BelongsToMany extends Relation
{
    /** @var string Relation class */
    protected const RELATION_CLASS = HasMany::class;

    protected $isOne = false;

    /** @var string Name of the join table or junction model class */
    protected $throughClass;

    /** @var ?string Alias for the join table or junction model class */
    protected ?string $throughAlias = null;

    /** @var Model The junction model */
    protected $through;

    /** @var string|array Column name(s) of the target model's foreign key found in the join table */
    protected $targetForeignKey;

    /** @var string|array Candidate key column name(s) in the target table which references the target foreign key */
    protected $targetCandidateKey;

    /**
     * Get the name of the join table or junction model class
     *
     * @return ?string
     */
    public function getThroughClass(): ?string
    {
        return $this->throughClass;
    }

    /**
     * Set the join table name or junction model class
     *
     * @param string $through
     *
     * @return $this
     */
    public function through(string $through): self
    {
        $this->throughClass = $through;

        return $this;
    }

    /**
     * Get the alias for the join table or junction model class
     *
     * @return string
     */
    public function getThroughAlias(): string
    {
        return $this->throughAlias ?? $this->getThrough()->getTableAlias();
    }

    /**
     * Set the alias for the join table or junction model class
     *
     * @param string $throughAlias
     *
     * @return $this
     */
    public function setThroughAlias(string $throughAlias): self
    {
        $this->throughAlias = $throughAlias;

        return $this;
    }

    /**
     * Get the junction model
     *
     * @return Model|Junction
     */
    public function getThrough(): Model
    {
        if ($this->through === null) {
            $throughClass = $this->getThroughClass();
            if ($throughClass === null) {
                throw new LogicException(
                    'You cannot use a many-to-many relation without a through class or a table name for the'
                    . ' junction model'
                );
            }

            if (class_exists($throughClass)) {
                $this->through = new $throughClass();
            } else {
                $this->through = (new Junction())
                    ->setTableName($throughClass);
            }
        }

        return $this->through;
    }

    /**
     * Set the junction model
     *
     * @param Model $through
     *
     * @return $this
     */
    public function setThrough(Model $through): self
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
    public function setTargetForeignKey($targetForeignKey): self
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
    public function setTargetCandidateKey($targetCandidateKey): self
    {
        $this->targetCandidateKey = $targetCandidateKey;

        return $this;
    }

    public function resolve()
    {
        $source = $this->getSource();

        $possibleCandidateKey = [$this->getCandidateKey()];
        $possibleForeignKey = [$this->getForeignKey()];

        $target = $this->getTarget();

        $possibleTargetCandidateKey = [$this->getTargetForeignKey() ?: static::getDefaultForeignKey($target)];
        $possibleTargetForeignKey = [$this->getTargetCandidateKey() ?: static::getDefaultCandidateKey($target)];

        $junction = $this->getThrough();

        if (! $junction instanceof Junction) {
            $relations = new Relations();
            $junction->createRelations($relations);

            if ($relations->has($source->getTableAlias())) {
                $sourceRelation = $relations->get($source->getTableAlias());

                $possibleCandidateKey[] = $sourceRelation->getForeignKey();
                $possibleForeignKey[] = $sourceRelation->getCandidateKey();
            }

            if ($relations->has($target->getTableAlias())) {
                $targetRelation = $relations->get($target->getTableAlias());

                $possibleTargetCandidateKey[] = $targetRelation->getCandidateKey();
                $possibleTargetForeignKey[] = $targetRelation->getForeignKey();
            }
        }

        $junctionClass = static::RELATION_CLASS;
        $toJunction = (new $junctionClass())
            ->setName($this->getThroughAlias())
            ->setSource($source)
            ->setTarget($junction)
            ->setCandidateKey($this->extractKey($possibleCandidateKey))
            ->setForeignKey($this->extractKey($possibleForeignKey));

        $targetClass = static::RELATION_CLASS;
        $toTarget = (new $targetClass())
            ->setName($this->getName())
            ->setSource($junction)
            ->setTarget($target)
            ->setCandidateKey($this->extractKey($possibleTargetCandidateKey))
            ->setForeignKey($this->extractKey($possibleTargetForeignKey));

        foreach ($toJunction->resolve() as $k => $v) {
            yield $k => $v;
        }

        foreach ($toTarget->resolve() as $k => $v) {
            yield $k => $v;
        }
    }

    protected function extractKey(array $possibleKey)
    {
        $filtered = array_filter($possibleKey);

        return array_pop($filtered);
    }
}
