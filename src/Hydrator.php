<?php

namespace ipl\Orm;

use function ipl\Stdlib\get_php_type;

/**
 * Hydrates raw database rows into concrete model instances.
 */
class Hydrator
{
    /** @var array Column to property resolution map */
    protected $columnToPropertyMap;

    /** @var array Property defaults in terms of key-value pairs */
    protected $defaults;

    /** @var array Additional hydration rules for the model's relations */
    protected $hydrators = [];

    /**
     * Set the column to property resolution map
     *
     * @param array $columnToPropertyMap
     *
     * @return $this
     */
    public function setColumnToPropertyMap(array $columnToPropertyMap)
    {
        $this->columnToPropertyMap = $columnToPropertyMap;

        return $this;
    }

    /**
     * Get defaults
     *
     * @return array
     */
    public function getDefaults()
    {
        return $this->defaults;
    }

    /**
     * Set defaults
     *
     * @param array $defaults
     *
     * @return $this
     */
    public function setDefaults(array $defaults)
    {
        $this->defaults = $defaults;

        return $this;
    }

    /**
     * Add a hydration rule
     *
     * @param string $path                Property path
     * @param string $propertyName        The name of the property to hydrate into
     * @param string $class               The class to use for the model instance
     * @param array  $columnToPropertyMap Column to property resolution map
     *
     * @return $this
     *
     * @throws \InvalidArgumentException If a hydrator for the given property already exists
     * @throws \InvalidArgumentException If the class to use for the model class is not a subclass of {@link Model}
     */
    public function add($path, $propertyName, $class, array $columnToPropertyMap)
    {
        if (isset($this->hydrators[$path])) {
            throw new \InvalidArgumentException("Hydrator for property '$propertyName' already exists");
        }

        // Test model class
        $model = new $class();
        if (! $model instanceof Model) {
            throw new \InvalidArgumentException(sprintf(
                '%s() expects parameter 2 to be a subclass of %s, %s given',
                __METHOD__,
                Model::class,
                get_php_type($model)
            ));
        }

        $this->hydrators[$path] = [$propertyName, $class, $columnToPropertyMap];

        //natcasesort($this->hydrators);

        return $this;
    }

    /**
     * Hydrate the given raw database rows into the specified model
     *
     * @param array $data
     * @param Model $model
     *
     * @return Model
     */
    public function hydrate(array $data, Model $model)
    {
        $properties = $this->extractAndMap($data, $this->columnToPropertyMap);

        foreach ($this->hydrators as $path => list($propertyName, $class, $columnToPropertyMap)) {
            $subject = &$properties;
            $parts = explode('.', $path);
            array_pop($parts);
            foreach ($parts as $part) {
                $subject = &$subject[$part];
            }
            /** @var Model $target */
            $target = new $class();
            /** @var array $columnToPropertyMap */
            $target->setProperties($this->extractAndMap($data, $columnToPropertyMap));
            $subject[$propertyName] = $target;
        }

        if ($this->defaults !== null) {
            $properties += $this->defaults;
        }

        $model->setProperties($properties);

        return $model;
    }

    /**
     * Extract and map the given data based on the specified column to property resolution map
     *
     * @param array $data
     * @param array $columnToPropertyMap
     *
     * @return array
     */
    protected function extractAndMap(array $data, array $columnToPropertyMap)
    {
        return array_combine(
            array_intersect_key($columnToPropertyMap, $data),
            array_intersect_key($data, $columnToPropertyMap)
        );
    }
}
