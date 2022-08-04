<?php

namespace ipl\Orm;

use InvalidArgumentException;
use LogicException;

class ColumnDefinition
{
    /** @var string The name of the column */
    protected $name;

    /** @var ?string The data type of the column */
    protected $type;

    /** @var ?string The label of the column */
    protected $label;

    /** @var ?array The values allowed for this column */
    protected $allowedValues;

    /**
     * Create a new column definition
     *
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Get the column name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the data type of the column
     *
     * @return ?string
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Set the data type of the column
     *
     * @param ?string $type
     *
     * @return $this
     */
    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get the column label
     *
     * @return ?string
     */
    public function getLabel(): ?string
    {
        return $this->label;
    }

    /**
     * Set the column label
     *
     * @param ?string $label
     *
     * @return $this
     */
    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Get the allowed values for this column
     *
     * @return ?array
     */
    public function getAllowedValues(): ?array
    {
        return $this->allowedValues;
    }

    /**
     * Set the allowed values for this column
     *
     * @param ?array $values
     *
     * @return $this
     */
    public function setAllowedValues(?array $values): self
    {
        $this->allowedValues = $values;

        return $this;
    }

    /**
     * Create a new column definition based on the given options
     *
     * @param array $options
     *
     * @return self
     */
    public static function fromArray(array $options): self
    {
        if (! isset($options['name'])) {
            throw new InvalidArgumentException('$options must provide a name');
        }

        $self = new static($options['name']);

        if (isset($options['type'])) {
            $self->setType($options['type']);
        }

        if (isset($options['label'])) {
            $self->setLabel($options['label']);
        }

        if (isset($options['allowed_values'])) {
            $self->setAllowedValues($options['allowed_values']);
        }

        return $self;
    }
}
