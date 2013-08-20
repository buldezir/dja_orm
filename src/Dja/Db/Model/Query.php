<?php
/**
 * User: Alexander.Arutyunov
 * Date: 10.07.13
 * Time: 17:47
 */

namespace Dja\Db\Model;

use Dja\Application\Application;
use Dja\Db\Model\Field\ForeignKey;
use Dja\Db\Pdo;
use Dja\Db\PdoStatement;

/**
 * todo: cleanup, may be extract query builder to other class
 * fluent interface
 * Class Query
 * @package Dja\Db\Model
 */
class Query implements \Countable, \Iterator
{
    const SELECT = 'select';
    const QUANTIFIER = 'quantifier';
    const COLUMNS = 'columns';
    const TABLE = 'table';
    const JOINS = 'joins';
    const WHERE = 'where';
    const GROUP = 'group';
    const HAVING = 'having';
    const ORDER = 'order';
    const LIMIT = 'limit';
    const OFFSET = 'offset';
    const QUANTIFIER_DISTINCT = 'DISTINCT';
    const QUANTIFIER_ALL = 'ALL';
    const JOIN_INNER = 'inner';
    const JOIN_OUTER = 'outer';
    const JOIN_LEFT = 'left';
    const JOIN_RIGHT = 'right';
    const SQL_STAR = '*';
    const ORDER_ASCENDING = 'ASC';
    const ORDER_DESCENDING = 'DESC';

    /**
     * @var string
     */
    protected $table;

    /**
     * @var array
     */
    protected $columns = array();

    /**
     * @var array
     */
    protected $joins = array();

    /**
     * @var array
     */
    protected $where = array();

    /**
     * @var array
     */
    protected $order = array();

    /**
     * @var null|array
     */
    protected $group = null;

    /**
     * @var null|string|array
     */
    protected $having = null;

    /**
     * @var int|null
     */
    protected $limit = null;

    /**
     * @var int|null
     */
    protected $offset = null;

    /**
     * @var Metadata
     */
    protected $metadata;

    /**
     * @var Pdo
     */
    protected $db;

    /**
     * @var string
     */
    protected $queryStringCache;

    /**
     * @var PdoStatement
     */
    protected $pdostatement = null;

    protected $pointer = 0;

    protected $rowCount = 0;

    protected $rowCache = array();

    protected $forceNoCache = false;

    protected $autoJoin = false;

    protected $autoJoinFilter = array();

    /**
     * for many rel query
     * @var array [$field, $model]
     */
    protected $relation;

    public function __construct(Metadata $metadata)
    {
        $this->metadata = $metadata;
        $this->table = $metadata->getDbTableName();
        $this->db = Application::getInstance()->db();
    }

    public function __toString()
    {
        return $this->buildQuery();
    }

    /**
     * @param array $a
     * @return $this
     */
    public function setRelation(array $a)
    {
        $this->relation = $a;
        return $this;
    }

    /**
     * $this->setRawSql('SELECT * FROM table')->values() === $db->query('SELECT * FROM table')->fetchAll()
     * @param $s
     * @return $this
     */
    public function setRawSql($s)
    {
        $this->queryStringCache = $s;
        return $this;
    }

    /**
     * @param $data
     * @return int
     * @throws \InvalidArgumentException
     */
    public function insert($data)
    {
        if ($data instanceof Model) {
            $data = $data->toArray();
        } elseif (is_array($data)) {

        } else {
            throw new \InvalidArgumentException('$data must be array or Model inst');
        }
        $keys = array();
        $values = array();
        foreach ($data as $key => $value) {
            $keys[] = $this->db->quoteId($key);
            $values[] = $this->db->quote($this->metadata->getField($key)->dbPrepValue($value));
        }

        $sql = "INSERT INTO " . $this->db->quoteId($this->table) . " (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ")";
//        dump($sql);
        $this->db->exec($sql);
        return $this->db->lastInsertId();
    }

    /**
     * @param array $data
     * @return int
     * @throws \Exception
     */
    public function update(array $data)
    {
        if (count($this->where) === 0) {
            throw new \Exception('must be WHERE conditions for update');
        }
        $set = array();
        foreach ($data as $key => $value) {
            $value = $this->metadata->getField($key)->dbPrepValue($value);
            $set[] = $this->db->placeHold($this->db->quoteId($key) . ' = ?', $value);
        }
        $sql = 'UPDATE ';
        $sql .= $this->db->quoteId($this->table) . ' ' . $this->db->quoteId('t');
        $sql .= ' ';
        $sql .= 'SET ' . implode(', ', $set);
        $sql .= ' ';
        $sql .= $this->buildWhere();
//        dump($sql); return;
        return $this->db->exec($sql);
    }

    /**
     * @return int
     * @throws \Exception
     */
    public function delete()
    {
        if (count($this->where) === 0) {
            throw new \Exception('must be WHERE conditions for delete');
        }
        $sql = 'DELETE FROM ';
        $sql .= $this->db->quoteId($this->table) . ' ' . $this->db->quoteId('t');
        $sql .= ' ';
        $sql .= $this->buildWhere();
        return $this->db->exec($sql);
    }

    /**
     * @param array $data
     * @return Model
     */
    public function create(array $data = array())
    {
        $class = $this->metadata->getModelClass();
        /** @var Model $model */
        $model = new $class();
        $model->setFromArray($data);
        if ($this->relation) {
            $this->add($model);
        } else {
            $model->save();
        }
        return $model;
    }

    /**
     * @return $this
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function remove()
    {
        if (!$this->relation) {
            throw new \Exception('This is not relation set');
        }
        if (func_num_args() === 0) {
            throw new \InvalidArgumentException('must be args > 0');
        }
        /** @var Model[] $args */
        $args = func_get_args();
        if (is_array($args[0])) {
            $args = $args[0];
        }
        list($field, $parentModel) = $this->relation;
        if ($field instanceof Field\ManyToManyRelation) {

        } else {
            $relVirtualField = $this->metadata->getField($field->to_field);
            foreach ($args as $model) {
                $model->__set($relVirtualField->name, null);
                //$model->save();
            }
        }
        return $this;
    }

    /**
     * @return $this
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function add()
    {
        if (!$this->relation) {
            throw new \Exception('This is not relation set');
        }
        if (func_num_args() === 0) {
            throw new \InvalidArgumentException('must be args > 0');
        }
        /** @var Model[] $args */
        $args = func_get_args();
        if (is_array($args[0])) {
            $args = $args[0];
        }
        list($field, $parentModel) = $this->relation;
        if ($field instanceof Field\ManyToManyRelation) {

        } else {
            $relVirtualField = $this->metadata->getField($field->to_field);
            foreach ($args as $model) {
                //var_dump($field->name, $field->self_field, $field->to_field, $this->metadata->getField($field->to_field)->name);
                $model->__set($relVirtualField->name, $parentModel);
                //$model->__set($field->self_field, $parentModel->__get($field->to_field));
                //$model->save();
            }
        }
        return $this;
    }

    /**
     * @param int $value
     * @throws \Exception
     * @return Model
     */
    public function get($value)
    {
        $pk = $this->metadata->getPrimaryKey();
        /** @var Query $qs */
        $qs = $this->resetAll()->filter([$pk => intval($value)]);
        $obj = $qs->current();
        if (!$obj) {
            throw new \Exception('Not found');
        }
        return $obj;
    }

    /**
     * @param int $limit
     * @param null $offset
     * @return $this
     */
    public function limit($limit, $offset = null)
    {
        $offset = $offset ? $offset : null;
        $limit = (int)$limit;
        $this->offset = $offset;
        $this->limit = $limit;
        return $this;
    }

    /**
     * array('is_active__exact' => 1, 'is_superuser__exact' => F('is_staff'))
     * array('pub_date__lte' => '2006-01-01')
     * array('user__is_active' => true)
     *
     * @param array $arguments
     * @param bool $negate
     * @return array
     * @throws \Exception
     */
    public function explaneArguments(array $arguments, $negate = false)
    {
        $result = array();
        foreach ($arguments as $lookup => $value) {
            // if exact lookuptype
            $lookupArr = explode('__', $lookup);
            $field = $this->metadata->getField($lookupArr[0]);
            $lookupType = end($lookupArr);
            if ($this->db->getSchema()->issetLookup($lookupType)) {
                $lookupType = array_pop($lookupArr);
            } else {
                $lookupType = 'exact';
            }
            if (!$field->isRelation() || $this->metadata->isLocal($lookupArr[0])) {
                $db_column = $field->db_column;
                $prefix = 't.';
            } else {
                $prefix = '';
                $db_column_a = implode('__', $lookupArr);
                if (!isset($this->columns[$db_column_a])) {
                    throw new \Exception('Cant lookup for related field without selectRelated()');
                }
                $db_column = $this->columns[$db_column_a];
            }

            $value = $field->dbPrepValue($value);
            list($db_column, $lookupQ, $value) = $this->db->getSchema()->getLookup($lookupType, $this->db->quoteId($prefix . $db_column), $value, $negate);
            if (is_array($value)) {
                $value = implode(', ', $value);
            } elseif ($value instanceof Expr || $value instanceof Query) {
                $value = strval($value);
            } else {
                $value = $this->db->quote($value);
            }
            $result[] = sprintf($db_column . ' ' . $lookupQ, $value);
        }
        return $result;
    }

    /**
     * no arguments for join all, or related field names
     * @return $this
     */
    public function selectRelated()
    {
        $this->autoJoin = true;
        foreach (func_get_args() as $j) {
            $this->autoJoinFilter[] = $j;
        }
        $this->buildJoins();
        return $this;
    }

    /**
     * where NOT *
     * @param array|string $arguments
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function exclude($arguments)
    {
        if (is_array($arguments)) {
            $arguments = $this->explaneArguments($arguments, true);
            foreach ($arguments as $cond) {
                $this->where[] = $cond;
            }
        } else {
            throw new \InvalidArgumentException("Invalid argument, array");
        }
        return $this;
    }

    /**
     * where *
     * @param $arguments
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function filter($arguments)
    {
        if (is_array($arguments)) {
            $arguments = $this->explaneArguments($arguments);
            foreach ($arguments as $cond) {
                $this->where[] = $cond;
            }
        } else {
            throw new \InvalidArgumentException("Invalid argument, array");
        }
        return $this;
    }

    /**
     * set Order part for query
     * @return $this
     */
    public function order()
    {
        $args = func_get_args();
        foreach ($args as $order) {
            if ($order{0} === '-') {
                $order = $this->db->quoteId(substr($order, 1)) . ' ' . self::ORDER_DESCENDING;
            } else {
                $order = $this->db->quoteId($order) . ' ' . self::ORDER_ASCENDING;
            }
            $this->order[] = $order;
        }
        return $this;
    }

    /**
     * return sql query string
     * @return string
     */
    public function buildQuery()
    {
        $joins = $this->autoJoin ? $this->buildJoins() : '';
        $sql = 'SELECT ';
        $sql .= $this->buildColumns();
        $sql .= ' FROM ';
        $sql .= $this->db->quoteId($this->table) . ' ' . $this->db->quoteId('t');
        $sql .= $joins;
        $sql .= ' ';
        $sql .= $this->buildWhere();
        $sql .= ' ';
        $sql .= $this->buildSort();
        $sql .= ' ';
        $sql .= $this->buildLimit();
        $this->queryStringCache = $sql;
        return $sql;
    }

    /**
     * limit part of sql query string
     * @return string
     */
    public function buildLimit()
    {
        if ($this->limit !== null) {
            if ($this->offset) {
                return 'LIMIT ' . $this->limit . ' OFFSET ' . $this->offset;
            } else {
                return 'LIMIT ' . $this->limit;
            }
        } else {
            return '';
        }
    }

    /**
     * order by part of sql query string
     * @return string
     */
    public function buildSort()
    {
        if (count($this->order) > 0) {
            return 'ORDER BY ' . implode(', ', $this->order) . '';
        } else {
            return '';
        }
    }

    /**
     * where part of sql query string
     * @return string
     */
    public function buildWhere()
    {
        if (count($this->where) > 0) {
            return 'WHERE (' . implode(') AND (', $this->where) . ')';
        } else {
            return '';
        }
    }

    /**
     * setup select columns and
     * joins part of sql query string
     * @return string
     */
    public function buildJoins()
    {
        $relFields = $this->metadata->getRelationFields();
        if (count($relFields) > 0) {
            $join = '';
            $i = 1;
            foreach ($relFields as $field) {
                if (!$field->canAutoJoin()) {
                    continue;
                }
                if (count($this->autoJoinFilter) > 0 && !in_array($field->name, $this->autoJoinFilter)) {
                    continue;
                }
                /** @var ForeignKey $field */
                $relClass = $field->relationClass;
                $tbl = $this->db->quoteId($relClass::metadata()->getDbTableName()) . ' ' . $this->db->quoteId('j' . $i);
                //$on = sprintf('%s = %s', $this->db->quoteId('t.' . $field->db_column), $this->db->quoteId('j' . $i . '.' . $field->to_field));
                $on = sprintf('%s = %s', $this->db->quoteId('t.' . $field->db_column), $this->db->quoteId('j' . $i . '.' . $field->to_field));
                $join .= ' LEFT JOIN ' . $tbl . ' ON ' . $on;
                foreach ($relClass::metadata()->getDbColNames() as $rCol) {
                    $this->columns[$field->name . '__' . $rCol] = 'j' . $i . '.' . $rCol;
                }
                $i++;
            }
            return $join;
        } else {
            return '';
        }
    }

    /**
     * select part of sql query string
     * @return string
     */
    public function buildColumns()
    {
        $parts = array();

        foreach ($this->metadata->getDbColNames() as $lCol) {
            $this->columns[] = 't.' . $lCol;
        }

        foreach ($this->columns as $alias => $colName) {
            if (!is_string($colName) && $colName instanceof Expr) {
                $parts[] = strval($colName);
            } else {
                if (is_int($alias)) {
                    $parts[] = $this->db->quoteId($colName);
                } else {
                    $parts[] = $this->db->quoteId($colName) . ' as ' . $this->db->quoteId($alias);
                }
            }
        }
        return implode(', ', $parts);
    }

    /**
     * lazy-load execution of sql query
     * @return PdoStatement|null|\PDOStatement
     */
    protected function exec()
    {
        if ($this->pdostatement === null) {
            if (!$this->queryStringCache) {
                $this->buildQuery();
            }
            //$this->pdostatement = $this->db->query($this->queryStringCache, \PDO::FETCH_CLASS, $this->metadata->getModelClass(), array('isNewRecord' => false));
            $this->pdostatement = $this->db->query($this->queryStringCache, \PDO::FETCH_ASSOC);
            $this->rowCount = $this->pdostatement->rowCount();
            //dump('Exec [' . $this->queryStringCache . ']');
            pr('Exec [' . $this->queryStringCache . ']');
        }
        return $this->pdostatement;
    }

    /**
     * returns array like from fetchAssoc, not iterator of models, from current query
     * @return array
     */
    public function values()
    {
        if (!$this->queryStringCache) {
            $this->buildQuery();
        }
        pr('Exec [' . $this->queryStringCache . ']');
        return $this->db->query($this->queryStringCache)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * returns key-value dictionary, from current query
     * @param $keyField
     * @param $valueField
     * @return array
     */
    public function valuesDict($keyField, $valueField)
    {
        $data = $this->values();
        $result = array();
        foreach ($data as $row) {
            $result[$row[$keyField]] = $row[$valueField];
        }
        return $result;
    }

    /**
     * force disabling caching records for multiple iterations
     * @return $this
     */
    public function noCache()
    {
        $this->forceNoCache = true;
        return $this;
    }

    /**
     * do nothing or returns clone of current Query without filtering
     * @return $this
     */
    public function all()
    {
        if (count($this->where) > 0 || $this->limit) {
            $q = clone $this;
            $q->reset(self::WHERE)->reset(self::LIMIT);
            return $q;
        } else {
            return $this;
        }
    }

    /**
     * make count query with current filters
     * @return int
     */
    public function doCount()
    {
        $q = clone $this;
        $q->reset(self::ORDER)->reset(self::LIMIT);

        $joins = $this->autoJoin ? $q->buildJoins() : '';
        $sql = 'SELECT COUNT(*) FROM ';
        $sql .= $this->db->quoteId($this->table) . ' ' . $this->db->quoteId('t');
        $sql .= $joins;
        $sql .= ' ';
        $sql .= $q->buildWhere();
        pr('Exec [' . $sql . ']');
        return $this->db->query($sql)->fetchColumn(0);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return Model
     */
    public function current()
    {
        if ($this->forceNoCache) {
            $cls = $this->metadata->getModelClass();
            return new $cls(false, $this->exec()->fetch());
        } else {
            if (!isset($this->rowCache[$this->pointer])) {
                $cls = $this->metadata->getModelClass();
                $this->rowCache[$this->pointer] = new $cls($this->exec()->fetch(), false);
            }
            return $this->rowCache[$this->pointer];
        }
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        $this->pointer++;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key()
    {
        return $this->pointer;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     */
    public function valid()
    {
        return $this->pointer < $this->rowCount;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        $this->exec();
        $this->pointer = 0;
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     */
    public function count()
    {
        $this->exec();
        return $this->rowCount;
    }

    /**
     * reset custom part of query and reset sql string, statement, row cache
     * @param string $part
     * @return $this
     */
    public function reset($part = null)
    {
        switch ($part) {
            case self::COLUMNS:
                $this->columns = array();
                break;
            case self::JOINS:
                $this->joins = array();
                break;
            case self::WHERE:
                $this->where = array();
                break;
            case self::GROUP:
                $this->group = null;
                break;
            case self::HAVING:
                $this->having = null;
                break;
            case self::LIMIT:
                $this->limit = null;
                $this->offset = null;
                break;
//            case self::OFFSET:
//                $this->offset = null;
//                break;
            case self::ORDER:
                $this->order = array();
                break;
            default:
                break;
        }
        $this->queryStringCache = null;
        $this->pdostatement = null;
        $this->pointer = 0;
        $this->rowCount = 0;
        $this->rowCache = array();
        return $this;
    }

    /**
     * equal to return new self($this->metadata);
     * @return $this
     */
    protected function resetAll()
    {
        $this->queryStringCache = null;
        $this->columns = array();
        $this->joins = array();
        $this->where = array();
        $this->limit = null;
        $this->offset = null;
        $this->order = array();

        $this->pdostatement = null;
        $this->pointer = 0;
        $this->rowCount = 0;
        $this->rowCache = array();
        return $this;
    }
}