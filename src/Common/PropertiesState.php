<?php

namespace ipl\Orm\Common;

use ipl\Stdlib\Properties;

trait PropertiesState
{
    use Properties {
        Properties::setProperty as private parentSetProperty;
    }

    /**
     * This model's modified properties
     *
     * @var array
     */
    protected $modified = [];

    /**
     * Flag that indicate whether this is a new record
     *
     * @var bool
     */
    protected $newRecord = false;

    /**
     * Flag whether this record has been already removed
     *
     * @var bool
     */
    protected $removed = false;

    /**
     * Flag that indicate whether the primary key of this model is auto incremented
     *
     * @var bool
     */
    protected $autoIncremented = true;

    protected function setProperty($key, $value)
    {
        $this->parentSetProperty($key, $value);

        return $this->setDirty($key);
    }

    /**
     * Get whether this model has any modified properties
     *
     * @param array $properties
     *
     * @return bool
     */
    protected function hasModified(...$properties)
    {
        if (empty($properties)) {
            return ! empty($this->modified);
        }

        foreach ($properties as $key) {
            if ($this->isModified($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all modified properties of this model
     *
     * @return array
     */
    public function getModifiedProperties()
    {
        return array_intersect_key($this->properties, $this->modified);
    }

    /**
     * Get the whether the given property is modified
     *
     * @param $key
     *
     * @return bool
     */
    protected function isModified($key)
    {
        return isset($this->modified[$key]) && isset($this->properties[$key]);
    }

    /**
     * Clear the modified properties of this model
     *
     * @return $this
     */
    protected function resetDirty()
    {
        $this->modified = [];

        return $this;
    }

    /**
     * Get whether this model's state has remained unchanged
     *
     * @param mixed ...$properties
     *
     * @return bool
     */
    public function isClean(...$properties): bool
    {
        return ! $this->isDirty(...$properties);
    }

    /**
     * Get whether this model's state has been modified
     *
     * @param mixed ...$properties
     *
     * @return bool
     */
    public function isDirty(...$properties): bool
    {
        return $this->hasModified(...$properties);
    }

    /**
     * Mark the given property as dirty/modified
     *
     * @param $property
     *
     * @return $this
     */
    public function setDirty($property)
    {
        $this->modified[$property] = true;

        return $this;
    }

    /**
     * Get whether this instance is a new record
     *
     * @return bool
     */
    public function isNewRecord(): bool
    {
        return $this->newRecord;
    }

    /**
     * @return bool
     */
    public function isRemoved(): bool
    {
        return $this->removed;
    }

    /**
     * Set whether this model's primary key is auto incremented
     *
     * @param bool $autoIncremented
     *
     * @return $this
     */
    public function setAutoIncremented(bool $autoIncremented): self
    {
        $this->autoIncremented = $autoIncremented;

        return $this;
    }

    /**
     * Get whether the primary key of this model is auto incremented
     *
     * @return bool
     */
    public function isAutoIncremented(): bool
    {
        return $this->autoIncremented;
    }
}
