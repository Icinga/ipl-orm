<?php

namespace ipl\Orm;

use ipl\Orm\Common\PropertiesWithDefaults;
use ipl\Orm\Compat\FilterProcessor;
use ipl\Sql\Connection;
use ipl\Sql\Delete;
use ipl\Sql\Insert;
use ipl\Sql\Update;
use ipl\Stdlib\Filter;

/**
 * Models represent single database tables or parts of it.
 * They are also used to interact with the tables, i.e. in order to query for data.
 */
abstract class Model implements \ArrayAccess, \IteratorAggregate
{
    use PropertiesWithDefaults;

    /** @var string Indicates whether insert() has successfully inserted a new entry into the DB */
    public const STATE_INSERTED = 'stateInserted';

    /** @var string Whether the update() has updated this model successfully */
    public const STATE_UPDATED = 'stateUpdated';

    /** @var string Whether remove() has removed this model successfully */
    public const STATE_REMOVED = 'stateRemoved';

    /** @var string Whether this model is in clean state/is unchanged */
    public const STATE_CLEAN = 'stateClean';

    final public function __construct(array $properties = null, bool $isNewRecord = true)
    {
        if ($this->hasProperties()) {
            $this->setProperties($properties);
        }

        $this->newRecord = $isNewRecord;
        $this->init();
    }

    /**
     * Get the related database table's name
     *
     * @return string
     */
    abstract public function getTableName();

    /**
     * Get the column name(s) of the primary key
     *
     * @return string|array Array if the primary key is compound, string otherwise
     */
    abstract public function getKeyName();

    /**
     * Get the model's queryable columns
     *
     * @return array
     */
    abstract public function getColumns();

    /**
     * Get the configured table alias. (Default {@see static::getTableName()})
     *
     * @return string
     */
    public function getTableAlias(): string
    {
        return $this->getTableName();
    }

    /**
     * Get the model's column definitions
     *
     * The array is indexed by column names, values are either strings (labels) or arrays of this format:
     *
     * [
     *  'label' => 'A Column',
     *  'type'  => 'enum(y,n)'
     * ]
     *
     * @return array
     */
    public function getColumnDefinitions()
    {
        return [];
    }

    /**
     * Get a query which is tied to this model and the given database connection
     *
     * @param Connection $db
     *
     * @return Query
     */
    public static function on(Connection $db)
    {
        return (new Query())
            ->setDb($db)
            ->setModel(new static());
    }

    /**
     * Get the prepared insert query of this model
     *
     * @param Connection $conn
     * @param array $properties
     *
     * @return ScopedQuery
     */
    public static function insert(Connection $conn, array $properties)
    {
        return (new static())
            ->setProperties($properties)
            ->prepareInsert($conn);
    }

    /**
     * Get the model's default sort
     *
     * @return array|string
     */
    public function getDefaultSort()
    {
        return [];
    }

    /**
     * Get the model's search columns
     *
     * @return array
     */
    public function getSearchColumns()
    {
        return [];
    }

    /**
     * Create the model's behaviors
     *
     * @param Behaviors $behaviors
     */
    public function createBehaviors(Behaviors $behaviors)
    {
    }

    /**
     * Create the model's defaults
     *
     * @param Defaults $defaults
     */
    public function createDefaults(Defaults $defaults)
    {
    }

    /**
     * Create the model's relations
     *
     * If your model should be associated to other models, override this method and create the model's relations.
     */
    public function createRelations(Relations $relations)
    {
    }

    /**
     * Initialize the model
     *
     * If you want to adjust the model after construction, override this method.
     */
    protected function init()
    {
    }

    /**
     * Save this model to the database
     *
     * Determines automagically whether it INSERT or UPDATE this model
     *
     * @param Connection $conn
     *
     * @return string
     */
    public function save(Connection $conn)
    {
        if ($this->isClean()) {
            return self::STATE_CLEAN;
        }

        if (! $this->isNewRecord()) { // Is modified
            $this->prepareUpdate($conn)->execute();

            $this->resetDirty();

            return self::STATE_UPDATED;
        } else {
            $this->prepareInsert($conn)->execute();

            if ($this->isAutoIncremented()) {
                $this->{$this->getKeyName()} = $conn->lastInsertId();
            }

            $this->newRecord = false;
            $this->resetDirty();

            return self::STATE_INSERTED;
        }
    }

    /**
     * Remove a database entry matching the given filter
     *
     * @param Connection $conn
     * @param ?Filter\Rule $filter
     *
     * @return string
     */
    public function remove(Connection $conn, Filter\Rule $filter = null)
    {
        if ($this->isNewRecord()) {
            throw new \LogicException('Cannot delete an entry which does not exists');
        }

        if ($this->isRemoved()) {
            throw new \LogicException('Cannot delete already deleted entry');
        }

        $delete = new Delete();
        $delete->from($this->getTableName());
        $this->applyFilter($delete, $filter);

        $query = new ScopedQuery($conn, $delete);
        $query->execute();

        $this->removed = true;

        return self::STATE_REMOVED;
    }

    protected function prepareUpdate(Connection $conn, Filter\Rule $filter = null)
    {
        if ($this->isNewRecord()) {
            throw new \LogicException('Cannot update a new entry');
        }

        if ($this->isRemoved()) {
            throw new \LogicException('Cannot update removed entry');
        }

        $update = new Update();
        $update
            ->table($this->getTableName())
            ->set($this->getModifiedProperties());

        $this->applyFilter($update, $filter);

        return new ScopedQuery($conn, $update);
    }

    protected function prepareInsert(Connection $conn)
    {
        if (! $this->isNewRecord()) {
            throw new \LogicException('Cannot insert already existing entry');
        }

        $properties = $this->getModifiedProperties();
        if (empty($properties)) {
            $properties = $this->properties;
        }

        if (! $this->isAutoIncremented() && ! isset($properties[$this->getKeyName()])) {
            throw new \Exception('Cannot insert entry without a primary key');
        }

        $insert = new Insert();
        $insert->into($this->getTableName());

        $insert->values($properties);

        return new ScopedQuery($conn, $insert);
    }

    /**
     * Apply the given filter or create custom filter based on the PK(s)
     *
     * @param Insert|Update|Delete $query
     * @param Filter\Rule|null $filter
     *
     * @return $this
     */
    protected function applyFilter($query, Filter\Rule $filter = null)
    {
        if (! $filter) {
            $filter = Filter::all();
            $keys = ! is_array($this->getKeyName()) ? [$this->getKeyName()] : $this->getKeyName();

            foreach ($keys as $key) {
                $filter->add(Filter::equal($key, (int) $this->{$key}));
            }
        }

        $where = FilterProcessor::assembleFilter($filter);
        $query->where(...array_reverse($where));

        return $this;
    }
}
