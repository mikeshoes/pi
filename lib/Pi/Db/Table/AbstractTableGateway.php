<?PHP
/**
 * Pi Engine (http://piengine.org)
 *
 * @link            http://code.piengine.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://piengine.org
 * @license         http://piengine.org/license.txt BSD 3-Clause License
 */

namespace Pi\Db\Table;

use ArrayObject;
use Laminas\Db\Sql\Join;
use Pi;
use Pi\Db\Sql\Sql;
use Pi\Db\Sql\Where;
use Laminas\Db\Metadata\Metadata;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\TableGateway\AbstractTableGateway as ZendAbstractTableGateway;
use Laminas\Db\TableGateway\Feature;

//use Laminas\Db\RowGateway\AbstractRowGateway;
//use Laminas\Db\Sql\Sql;

/**
 * Pi Table Gateway
 *
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
abstract class AbstractTableGateway extends ZendAbstractTableGateway
{
    /**
     * Class for select result prototype
     *
     * @var string
     */
    protected $resultSetClass;

    /**
     * Class for row or row gateway
     *
     * @var string
     */
    protected $rowClass;

    /**
     * Table fields/columns. Will be fetched from metadata if not specified
     *
     * @var string[]
     */
    protected $columns = [];

    /**
     * Non-scalar columns to be encoded before saving to DB
     * and decoded after fetching from DB,
     * specified as pairs of column name and bool value:
     *
     *  - true: to convert to associative array for decode;
     *  - false: keep as array object.
     * @var array
     */
    protected $encodeColumns
        = [
            // column name => convert to associative array?
            //'col_array'     => true,
            //'col_object'    => false,
        ];

    /**
     * Primary key column
     *
     * @var string
     */
    protected $primaryKeyColumn;

    /** @var Metadata Table metadata */
    protected $metadata;

    /**
     * Constructor
     *
     * @param array $options
     */
    public function __construct($options = [])
    {
        $this->setup($options);
        $this->initialize();
    }

    /**
     * Setup model
     *
     * @param array $options
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setup($options = [])
    {
        $tablePrefix = '';
        if (isset($options['prefix'])) {
            $tablePrefix = $options['prefix'];
            unset($options['prefix']);
        }
        $tableName = '';
        if (isset($options['name'])) {
            $tableName = $options['name'];
            unset($options['name']);
        }

        // process features
        if (isset($options['features'])) {
            if ($options['features'] instanceof Feature\AbstractFeature) {
                $options['features'] = [$options['features']];
            }
            if (is_array($options['features'])) {
                $this->featureSet = new Feature\FeatureSet(
                    $options['features']
                );
            } elseif ($options['features'] instanceof Feature\FeatureSet) {
                $this->featureSet = $options['features'];
            } else {
                throw new \InvalidArgumentException(
                    'TableGateway expects $options["feature"] to be'
                    . ' an instance of an AbstractFeature or a FeatureSet, '
                    . 'or an array of AbstractFeatures'
                );
            }
            unset($options['features']);
        }

        // Properties: table, schema, adapter, masterAdapter, slaveAdapter,
        // sql, selectResultPrototype, resultSetClass, rowClass,
        // primaryKeyColumn
        foreach ($options as $key => $value) {
            $this->{$key} = $value;
        }
        // Setup table
        if (!$this->table && $tableName) {
            $this->table = $tableName;
        }
        if ($tablePrefix) {
            $this->table = $tablePrefix . $this->table;
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function initialize()
    {
        if ($this->isInitialized) {
            return;
        }

        $this->sql = $this->sql ?: new Sql($this->adapter, $this->table);

        if (!$this->resultSetPrototype) {
            $rowObjectPrototype = $this->createRow();
            if ($this->resultSetClass) {
                $resultSetPrototype
                    = new $this->resultSetClass(null, $rowObjectPrototype);
            } else {
                $resultSetPrototype = new ResultSet(null, $rowObjectPrototype);
            }
            $this->resultSetPrototype = $resultSetPrototype;
        }

        parent::initialize();
    }

    /**
     * Set adapter
     *
     * @param Adapter $adapter
     * @return void
     */
    public function setAdapter(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * {@inheritDoc}
     */
    public function select($where = null)
    {
        if (!$this->isInitialized) {
            $this->initialize();
        }
        if (null === $where) {
            return $this->sql->select();
        }

        return parent::select($where);
    }

    /**#@APIs+ */
    /**
     * Creates Row object
     *
     * @param array|null $data
     *
     * @return RowGateway|Row
     */
    public function createRow($data = null)
    {
        if (!$this->rowClass) {
            $row = new ArrayObject;
        } elseif (is_subclass_of(
            $this->rowClass,
            'Laminas\Db\RowGateway\AbstractRowGateway'
        )) {
            $row = new $this->rowClass(
                $this->primaryKeyColumn,
                $this,
                $this->sql
            );
            if ($this->encodeColumns) {
                $row->setEncodeColumns($this->encodeColumns);
            }
        } else {
            $row = new $this->rowClass;
        }
        if (null !== $data) {
            $row->populate($data, false);
        }

        return $row;
    }

    /**
     * Get column names
     *
     * @param bool $fetch Fetch from metadata if not specified
     *
     * @return string[]
     */
    public function getColumns($fetch = false)
    {
        if (!$this->columns && $fetch) {
            $columns = $this->metadata()->getColumnNames($this->table);
        } else {
            $columns = $this->columns;
        }

        return $columns;
    }

    /**
     * Set columns to be encode/decode
     *
     * @param array $columns
     * @return $this
     */
    public function setEncodeColumns(array $columns)
    {
        $this->encodeColumns = $columns;

        return $this;
    }

    /**
     * Quote identifier
     *
     * @param  string $identifier
     * @return string
     */
    public function quoteIdentifier($identifier)
    {
        return $this->adapter->getPlatform()->quoteIdentifier($identifier);
    }

    /**
     * Quote value
     *
     * @param  string $value
     * @return string
     */
    public function quoteValue($value)
    {
        return $this->adapter->getPlatform()->quoteValue($value);
    }

    /**
     * Format parameter name
     *
     * @param string $name
     * @param string|null $type
     * @return string
     */
    public function formatParameterName($name, $type = null)
    {
        return $this->adapter->getDriver()->formatParameterName($name, $type);
    }

    /**
     * Fetches row(s) by primary key or specified column
     *
     * The argument specifies one or more key value(s).
     * To find multiple rows, the argument must be an array.
     *
     * The find() method returns a ResultSet object
     * if key array is provided or a Row object
     * if a single key value is provided.
     *
     * @param array|string|int $key The value(s) of the key
     * @param string|null $column Column name of the key
     * @return ResultSet|Row Row(s) matching the criteria.
     * @throws \Exception Throw exception if column is not specified
     */
    public function find($key, $column = null)
    {
        $column = $column ?: $this->primaryKeyColumn;
        if (!$column) {
            throw new \Exception('No column is specified.');
        }
        $isScalar = false;
        if (!is_array($key)) {
            $isScalar = true;
            $key      = [$key];
        }
        $where = new Where;
        if (count($key) == 1) {
            $where->equalTo($column, $key[0]);
        } else {
            $where->in($column, $key);
        }
        $select    = $this->select()->where($where); //->limit(1);
        $resultSet = $this->selectWith($select);

        $result = $isScalar ? $resultSet->current() : $resultSet;

        return $result;
    }

    /**
     * Get Metadata
     *
     * @return Metadata
     */
    public function metadata()
    {
        if (!$this->metadata) {
            $this->metadata = new Metadata($this->adapter);
        }

        return $this->metadata;
    }

    /**
     * Add a feature to FeatureSet
     *
     * @param string $name
     * @return $this
     */
    public function addFeature($name)
    {
        $featureClass = sprintf('%s\Feature\\%sFeature', __NAMESPECE, $name);
        if (!class_exists($featureClass)) {
            $featureClass = sprintf(
                'Laminas\Db\TableGateway\Feature\\%sFeature',
                $name
            );
        }
        $this->featureSet->addFeature(new $featureClass);

        return $this;
    }

    /**
     * Fetch count against condition
     *
     * @param array|Where $where
     * @param array|string $params
     *
     * @return bool|int|ResultSet
     */
    public function count($where = [], $params = null)
    {
        $group = $having = $limit = null;
        if ($params) {
            if (is_string($params)) {
                $group = $params;
            } else {
                $keys = array_keys($params);
                if (is_int($keys[0])) {
                    $group = $params;
                } else {
                    extract($params);
                }
            }
        }

        $columns = ['count' => Pi::db()->expression('COUNT(*)')];
        if ($group) {
            $columns += (array)$group;
        }
        $select = $this->select();
        $select->columns($columns);
        $select->where($where);

        if ($group) {
            $select->group($group);
            if ($limit) {
                $select->limit($limit);
                $select->order('count DESC');
            }
        }
        if ($having) {
            $select->having($having);
        }
        try {
            $rowset = $this->selectWith($select);
            if ($group) {
                $result = $rowset;
            } else {
                $row    = $rowset->current();
                $result = (int)$row['count'];
            }
        } catch (\Exception $e) {
            $result = false;
        }

        return $result;
    }

    /**
     * Perform an increment operation upon certain integer fields
     *
     * @param string|string[] $columns Column(s) to be incremented
     * @param array|Where $where
     * @param int $increment
     *
     * @return int
     */
    public function increment($columns, $where = null, $increment = 1)
    {
        $operator = ($increment > 0) ? '+' : '-';
        $segment  = $operator . ' ' . abs($increment);
        $set      = [];
        foreach ((array)$columns as $column) {
            $set[$column] = Pi::db()->expression($column . $segment);
        }
        if (null !== $where && !$where instanceof Where) {
            $where = Pi::db()->where($where);
        }
        $result = $this->update($set, $where);

        return $result;
    }

    /**
     * Update
     *
     * @param  array $set
     * @param  string|array|\Closure $where
     * @param  null|array $joins
     * @return int
     */
    public function update($set, $where = null, array $joins = null)
    {
        if (!$this->isInitialized) {
            $this->initialize();
        }
        $sql = $this->sql;
        $update = $sql->update();
        $update->set($set);
        if ($where !== null) {
            $update->where($where);
        }

        if ($joins) {
            foreach ($joins as $join) {
                $type = isset($join['type']) ? $join['type'] : Join::JOIN_INNER;
                $update->join($join['name'], $join['on'], $type);
            }
        }

        $response = $this->executeUpdate($update);

        /**
         * Trigger events after update
         */
        Pi::service('observer')->triggerUpdatedTable($this, $set, $where);

        return $response;
    }

    /**
     * Delete
     *
     * @param array|\Closure|string|\Laminas\Db\Sql\Where $where
     *
     * @return int
     */
    public function delete($where)
    {
        $results = $this->select($where);

        foreach($results as $row) {
            Pi::service('observer')->triggerDeletedRow($row, $row->toArray());
        }

        return parent::delete($where);
    }
}
