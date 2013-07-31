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

class Expr
{
    public $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    function __toString()
    {
        return $this->value;
    }
}

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
     * @param string $q
     * @return \Dja\Db\PdoStatement
     */
    public function rawQuery($q)
    {
        // |\PDO::FETCH_PROPS_LATE
        return $this->db->query($q, \PDO::FETCH_CLASS, $this->metadata->getModelClass(), array('isNewRecord' => false));
    }

    /**
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
        $keys = array_map(array($this->db, 'quoteId'), array_keys($data));
        $values = array_map(array($this->db, 'quote'), $data);
        $sql = "INSERT INTO ".$this->db->quoteId($this->table)." (".implode(', ', $keys).") VALUES (".implode(', ', $values).")";
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
            $set[] = $this->db->placeHold($this->db->quoteId($key).' = ?', $value);
        }
        $sql = 'UPDATE ';
        $sql .= $this->db->quoteId($this->table).' '.$this->db->quoteId('t');
        $sql .= ' ';
        $sql .= 'SET '.implode(', ', $set);
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
        $sql .= $this->db->quoteId($this->table).' '.$this->db->quoteId('t');
        $sql .= ' ';
        $sql .= $this->buildWhere();
        return $this->db->exec($sql);
    }

    /**
     * @param int $value
     * @throws \Exception
     * @return Model
     */
    public function get($value)
    {
        $pk = $this->metadata->getPrimaryKey();
        $obj = $this->resetAll()->filter([$pk => (int)$value])->current();
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
        $limit  = (int) $limit;
        $this->offset = $offset;
        $this->limit = $limit;
        return $this;
    }

    /**
     * array('is_active__exact' => 1, 'is_superuser__exact' => F('is_staff'))
     * array('pub_date__lte' => '2006-01-01')
     */
    public function explaneArguments(array $arguments)
    {
        $result = array();
        foreach ($arguments as $lookup => $value) {
            // if exact lookuptype
            if (strpos($lookup, '__') === false) {
                $lookupArr  = array($lookup);
                $lookupType = 'exact';
            } else {
                $lookupArr  = explode('__', $lookup);
                $lookupType = array_pop($lookupArr);
            }

            if (count($lookupArr) > 1) { // if join lookup
                throw new \Exception('Join lookups not implemented !');
            } else {
                $colName = $lookupArr[0];
            }
            $f = $this->metadata->getField($colName)->db_column;
            list($f, $lookupQ, $value) = $this->db->getSchema()->getLookup($lookupType, $this->db->quoteId('t.'.$f), $value);
            if (is_array($value)) {
                $value = implode(', ', $value);
            } elseif ($value instanceof Expr) {
                $value = strval($value);
            } else {
                $value = $this->db->quote($value);
            }
            $result[] = sprintf($f.' '.$lookupQ, $value);
        }
        return $result;
    }

    /**
     * @param array|string $arguments
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
        } elseif (is_string($arguments)) {
            $this->where[] = $arguments;
        } else {
            throw new \InvalidArgumentException("Invalid argument, must be string or array");
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
                $order = $this->db->quoteId(substr($order, 1)).' '.self::ORDER_DESCENDING;
            } else {
                $order = $this->db->quoteId($order).' '.self::ORDER_ASCENDING;
            }
            $this->order[] = $order;
        }
        return $this;
    }

    public function buildQuery()
    {
        $joins = $this->buildJoins();
        $sql  = 'SELECT ';
        $sql .= $this->buildColumns();
        $sql .= ' FROM ';
        $sql .= $this->db->quoteId($this->table).' '.$this->db->quoteId('t');
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

    protected function buildLimit()
    {
        if ($this->limit !== null) {
            if ($this->offset) {
                return 'LIMIT '.$this->limit.' OFFSET '.$this->offset;
            } else {
                return 'LIMIT '.$this->limit;
            }
        } else {
            return '';
        }
    }

    protected function buildSort()
    {
        if (count($this->order) > 0) {
            return 'ORDER BY '.implode(', ', $this->order).'';
        } else {
            return '';
        }
    }

    protected function buildWhere()
    {
        if (count($this->where) > 0) {
            return 'WHERE ('.implode(') AND (', $this->where).')';
        } else {
            return '';
        }
    }

    protected function buildJoins()
    {
        $relFields = $this->metadata->getRelationFields();
        if (count($relFields) > 0) {
            $join = '';
            $i = 1;
            foreach ($relFields as $field) {
                /** @var ForeignKey $field */
                $relClass = $field->relationClass;
                $tbl = $this->db->quoteId($relClass::metadata()->getDbTableName()).' '.$this->db->quoteId('j'.$i);
                $on = sprintf('%s = %s', $this->db->quoteId('t.'.$field->db_column), $this->db->quoteId('j'.$i.'.'.$field->to_field));
                $join .= ' LEFT JOIN '.$tbl.' ON '.$on;
                foreach ($relClass::metadata()->getDbColNames() as $rCol) {
                    $this->columns[$field->name.'__'.$rCol] = 'j'.$i.'.'.$rCol;
                }
                $i++;
            }
            return $join;
        } else {
            return '';
        }
    }

    protected function buildColumns()
    {
        $parts = array();

        foreach ($this->metadata->getDbColNames() as $lCol) {
            $this->columns[] = 't.'.$lCol;
        }

        foreach ($this->columns as $alias => $colName) {
            if (!is_string($colName) && $colName instanceof Expr) {
                $parts[] = strval($colName);
            } else {
                if (is_int($alias)) {
                    $parts[] = $this->db->quoteId($colName);
                } else {
                    $parts[] = $this->db->quoteId($colName).' as '.$this->db->quoteId($alias);
                }
            }
        }
        return implode(', ', $parts);
    }

    /**
     * @return mixed
     * @throws \BadMethodCallException
     */
    /*protected function bind()
    {
        $args = func_get_args();
        if (count($args) < 2) {
            throw new \BadMethodCallException('Must be string and minimum 1 argument');
        }
        $string = array_shift($args);
        if (substr_count($string, '?') != count($args)) {
            throw new \BadMethodCallException('Number or placeholders didnt match with number of parameters');
        }
        foreach ($args as $value) {
            $string = substr_replace($string, $value, strpos($string, '?'), 1);
        }
        return $string;
    }*/

    /**
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
            dump('Exec ['.$this->queryStringCache.']');
        }
        return $this->pdostatement;
    }

    /**
     * @return array
     */
    public function values()
    {
        if (!$this->queryStringCache) {
            $this->buildQuery();
        }
        return $this->db->query($this->queryStringCache)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function execCached()
    {
        if ($this->forceNoCache) {
            throw new \Exception('forceNoCache enabled');
        }
        $this->rowCache = $this->exec()->fetchAll();
        return $this;
    }

    public function noCache()
    {
        $this->forceNoCache = true;
        return $this;
    }

    /**
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
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     */
    public function current()
    {
        if ($this->forceNoCache) {
            return $this->exec()->fetch();
        } else {
            if (!isset($this->rowCache[$this->pointer])) {
                $cls = $this->metadata->getModelClass();
                $this->rowCache[$this->pointer] = new $cls(false, $this->exec()->fetch());
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
     * @param string $part
     * @return $this
     */
    public function reset($part)
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
        }
        $this->queryStringCache = null;
        $this->pdostatement = null;
        $this->pointer = 0;
        $this->rowCount = 0;
        $this->rowCache = array();
        return $this;
    }

    /**
     * @return $this
     */
    protected function resetAll()
    {
        $this->queryStringCache = null;
        $this->columns = array(self::SQL_STAR);
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