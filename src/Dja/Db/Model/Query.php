<?php
/**
 * User: Alexander.Arutyunov
 * Date: 10.07.13
 * Time: 17:47
 */

namespace Dja\Db\Model;

use Dja\Db\Model\Field\ForeignKey;
use Dja\Db\Model\Lookup\LookupAbstract;

/**
 * fluent interface
 * Class Query
 * @package Dja\Db\Model
 */
class Query implements \Countable, \Iterator
{
    const ORDER_ASCENDING = 'ASC';
    const ORDER_DESCENDING = 'DESC';

    protected static $lookUp;

    /**
     * @var string
     */
    protected $table;

    /**
     * @var array
     */
    protected $columns = [];

    /**
     * @var array
     */
    protected $aliasHash = [];

    /**
     * @var array
     */
    protected $joins = [];

    /**
     * @var Metadata
     */
    protected $metadata;

    /**
     * cache
     * @var string
     */
    protected $modelClassName;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $db;

    /**
     * @var \Doctrine\DBAL\Query\QueryBuilder
     */
    protected $qb;

    /**
     * @var string
     */
    protected $queryStringCache;

    /**
     * @var \Doctrine\DBAL\Statement
     */
    protected $statement = null;

    protected $pointer = 0;

    protected $rowCount = 0;

    protected $rowCache = [];

    protected $forceNoCache = false;

    protected $autoJoin = false;

    protected $autoJoinFilter = [];

    /**
     * for many rel query
     * @var array [$field, $model]
     */
    protected $relation;

    public function __construct(Metadata $metadata)
    {
        $this->metadata = $metadata;
        $this->modelClassName = $metadata->getModelClass();
        $this->table = $metadata->getDbTableName();
        $this->db = $metadata->getDbConnection();
        $this->resetAll();
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
        $dataReady = [];
        foreach ($data as $key => $value) {
            $dataReady[$this->qi($key)] = $this->db->quote($this->metadata->getField($key)->dbPrepValue($value));
        }
        $this->db->insert($this->qi($this->table), $dataReady);
        return $this->db->lastInsertId();
    }

    /**
     * @param array $data
     * @return int
     * @throws \Exception
     */
    public function update(array $data)
    {
        if (count($this->qb->getQueryPart('where')) === 0) {
            throw new \Exception('must be WHERE conditions for update');
        }
        $this->qb->update($this->qi($this->table), $this->qi('t'));
        foreach ($data as $key => $value) {
            $this->qb->set($this->qi($key), $this->db->quote($this->metadata->getField($key)->dbPrepValue($value)));
        }
        echo $this->qb->getSQL();
        return $this->qb->execute();
    }

    /**
     * @return int
     * @throws \Exception
     */
    public function delete()
    {
        if (count($this->qb->getQueryPart('where')) === 0) {
            throw new \Exception('must be WHERE conditions for delete');
        }
        $this->qb->delete($this->qi($this->table), $this->qi('t'));
        return $this->qb->execute();
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
     * @param $value
     * @return Model
     * @throws \Exception
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
     * @param $limit
     * @param null $offset
     * @return $this
     */
    public function limit($limit, $offset = null)
    {
        $this->qb->setFirstResult($offset)->setMaxResults($limit);
        return $this;
    }

    /**
     * array('is_active__exact' => 1, 'is_superuser__exact' => F('is_staff'))
     * array('pub_date__lte' => '2006-01-01')
     * array('user__is_active' => true)
     * @param array $arguments
     * @param bool $negate
     * @return array
     * @throws \Exception
     */
    public function explaneArguments(array $arguments, $negate = false)
    {
        $this->buildJoins(); // !!!!!!!!!!!!!!!!!!
        $aHashFlip = array_flip($this->aliasHash);

        $result = [];
        foreach ($arguments as $lookup => $value) {
            // if exact lookuptype
            $lookupArr = explode('__', $lookup);
            $lookupType = end($lookupArr);
            if ($this->lookuper()->issetLookup($lookupType)) {
                $lookupType = array_pop($lookupArr);
            } else {
                $lookupType = 'exact';
            }

            $field = $this->findLookUpField($this->metadata, $lookupArr); //$this->metadata->getField($lookupArr[0]);

            if ($this->metadata->hasFieldObj($field)) {
                $db_column = $field->db_column;
                $prefix = 't.';
            } else {
                $prefix = '';
                $db_column_a = implode('__', $lookupArr);
                if (!isset($aHashFlip[$db_column_a])) {
                    throw new \Exception('Cant lookup for related field without selectRelated()');
                }
                $db_column = $this->columns[$aHashFlip[$db_column_a]];
            }

            $value = $field->dbPrepLookup($value);
            list($db_column, $lookupQ, $value) = $this->lookuper()->getLookup($lookupType, $this->qi($prefix . $db_column), $value, $negate);
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
     * @param Metadata $md
     * @param array $lookupArr
     * @return Field\Base
     */
    protected function findLookUpField(Metadata $md, array $lookupArr)
    {
        $f = array_shift($lookupArr);
        $field = $md->getField($f);
        if (count($lookupArr) && $field->isRelation()) {
            $field = $this->findLookUpField($field->getRelationMetadata(), $lookupArr);
        }
        return $field;
    }

    /**
     * no arguments for join all, or related field names
     * @return $this
     */
    public function selectRelated()
    {
        if (func_num_args() === 1 && is_array(func_get_arg(0))) {
            $args = func_get_arg(0);
        } else {
            $args = func_get_args();
        }
        foreach ($args as $j) {
            $this->autoJoinFilter[] = $j;
        }
        $this->autoJoin = true;
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
                $this->qb->andWhere($cond);
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
                $this->qb->andWhere($cond);
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
                $this->qb->addOrderBy($this->qi(substr($order, 1)), self::ORDER_DESCENDING);
            } else {
                $this->qb->addOrderBy($this->qi($order), self::ORDER_ASCENDING);
            }
        }
        return $this;
    }

    /**
     * return sql query string
     * @return string
     */
    public function buildQuery()
    {
        if ($this->autoJoin) {
            $this->buildJoins();
            foreach ($this->joins as $row) {
                $this->qb->leftJoin($this->qi($row['selfAlias']), $this->qi($row['joinTable']), $this->qi($row['joinAlias']), $row['on']);
            }
        }
        $this->buildColumns();
        $this->queryStringCache = $this->qb->getSQL();
        return $this->queryStringCache;
    }

    /**
     * setup select columns and
     * joins part of sql query string
     */
    public function buildJoins()
    {
        if (!empty($this->joins)) {
            return;
        }
        $maxDepth = 3;
        $processRelations = function ($md, $curPrefix = '', $curAlias = 't', $curDepth = 0) use (&$processRelations, $maxDepth) {
            /** @var Metadata $md */
            static $i = 1;
            if ($curDepth > $maxDepth) {
                echo 'join depth err. ';
                return [];
            }
            $relFields = $md->getRelationFields();
            $joinData = [];
            foreach ($relFields as $field) {
                if (!$field->canAutoJoin()) {
                    return;
                }
                $columns = [];
                $aliasHash = [];
                /** @var ForeignKey $field */
                $relClass = $field->relationClass;
                $alias = 'j' . $i;
                $prefix = $curPrefix . $field->name . '__';
                $on = sprintf('%s = %s', $this->qi($curAlias . '.' . $field->db_column), $this->qi($alias . '.' . $field->to_field));
                foreach ($relClass::metadata()->getDbColNames() as $rCol) {
                    $h = md5($prefix . $rCol);
                    $aliasHash[$h] = $prefix . $rCol;
                    $columns[$h] = $alias . '.' . $rCol;
                }
                $i++;
                $joinData[] = [
                    'prefix' => $prefix,
                    'selfAlias' => $curAlias,
                    'joinTable' => $relClass::metadata()->getDbTableName(),
                    'joinAlias' => $alias,
                    'on' => $on,
                    'columns' => $columns,
                    'aliasHash' => $aliasHash,
                ];
                foreach ($processRelations($relClass::metadata(), $prefix, $alias, $curDepth + 1) as $tmp) {
                    $joinData[] = $tmp;
                }
            }
            return $joinData;
        };

        $this->joins = $processRelations($this->metadata);

        foreach ($this->joins as $row) {
            foreach ($row['columns'] as $selectF => $selectA) {
                $this->columns[$selectF] = $selectA;
            }
            foreach ($row['aliasHash'] as $h => $f) {
                $this->aliasHash[$h] = $f;
            }
        }
        //var_dump($processRelations($this->metadata));
    }

    /**
     * @param array $data
     * @return array
     */
    protected function mapAliasHash($data)
    {
        $result = [];
        foreach ($data as $k => $v) {
            if (isset($this->aliasHash[$k])) {
                $result[$this->aliasHash[$k]] = $v;
            } else {
                $result[$k] = $v;
            }
        }
        return $result;
    }

    /**
     * @return array
     */
    public function getAliasHash()
    {
        return $this->aliasHash;
    }

    /**
     * select part of sql query string
     * @return string
     */
    public function buildColumns()
    {
        $parts = [];

        foreach ($this->metadata->getDbColNames() as $lCol) {
            $this->columns[] = 't.' . $lCol;
        }

        foreach ($this->columns as $alias => $colName) {
            if (!is_string($colName) && $colName instanceof Expr) {
                $parts[] = strval($colName);
            } else {
                if (is_int($alias)) {
                    $parts[] = $this->qi($colName);
                } else {
                    $parts[] = $this->qi($colName) . ' as ' . $this->qi($alias);
                }
            }
            $this->qb->addSelect(end($parts));
        }
        return implode(', ', $parts);
    }

    /**
     * lazy-load execution of sql query
     * @return statement|null|\statement
     */
    protected function exec()
    {
        if ($this->statement === null) {
            if (!$this->queryStringCache) {
                $this->buildQuery();
            }
            //$this->statement = $this->db->query($this->queryStringCache, \PDO::FETCH_CLASS, $this->metadata->getModelClass(), array('isNewRecord' => false));
            $this->statement = $this->db->query($this->queryStringCache, \PDO::FETCH_ASSOC);
            $this->rowCount = $this->statement->rowCount();
            //dump('Exec [' . $this->queryStringCache . ']');
            //pr('Exec [' . $this->queryStringCache . ']');
        }
        return $this->statement;
    }

    /**
     * returns array like from fetchAssoc, not iterator of models, from current query
     * @param bool $applyDataFilter
     * @return array
     */
    public function values($applyDataFilter = false)
    {
        if (!$this->queryStringCache) {
            $this->buildQuery();
        }
//        pr('Exec [' . $this->queryStringCache . ']');
        $stmt = $this->db->query(str_replace([
            ':t',
            ':pk',
        ], [
            $this->qi($this->table),
            $this->qi($this->metadata->getPrimaryKey()),
        ], $this->queryStringCache));
        if ($applyDataFilter) {
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $i => $row) {
                $rows[$i] = $this->metadata->filterData($row);
            }
            return $rows;
        } else {
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
    }

    /**
     * returns key-value dictionary, from current query
     * @param $keyField
     * @param $valueField
     * @param bool $applyDataFilter
     * @return array
     */
    public function valuesDict($keyField, $valueField, $applyDataFilter = false)
    {
        $data = $this->values($applyDataFilter);
        $result = [];
        foreach ($data as $row) {
            $result[$row[$keyField]] = $row[$valueField];
        }
        return $result;
    }

    /**
     * force disabling caching records for massive iterations
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
//            $q->reset(self::WHERE)->reset(self::LIMIT);
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
        $qb = clone $this->qb;
        $sql = $qb->resetQueryPart('orderBy')->setMaxResults(null)->setFirstResult(null)->select('COUNT(*)')->getSQL();
//        pr('Exec [' . $sql . ']');
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
            $cls = $this->modelClassName;
            return new $cls($this->mapAliasHash($this->exec()->fetch()), false, true);
        } else {
            if (!isset($this->rowCache[$this->pointer])) {
                $cls = $this->modelClassName;
                $this->rowCache[$this->pointer] = new $cls($this->mapAliasHash($this->exec()->fetch()), false);
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
        $this->qb->resetQueryPart($part);

        $this->queryStringCache = null;
        $this->columns = [];
        $this->joins = [];
        $this->aliasHash = [];

        $this->pointer = 0;
        $this->rowCount = 0;
        $this->rowCache = [];

        return $this;
    }

    /**
     * equal to return new self($this->metadata);
     * @return $this
     */
    protected function resetAll()
    {
        $this->qb = $this->db->createQueryBuilder();
        $this->qb->from($this->qi($this->table), $this->qi('t'));

        $this->queryStringCache = null;
        $this->columns = [];
        $this->joins = [];
        $this->aliasHash = [];

        $this->pointer = 0;
        $this->rowCount = 0;
        $this->rowCache = [];
        return $this;
    }

    /**
     * alias for $this->db->quoteIdentifier
     * @param $v
     * @return string
     */
    protected function qi($v)
    {
        return $this->db->quoteIdentifier($v);
    }

    /**
     * @return LookupAbstract
     */
    protected function lookuper()
    {
        if (!isset(self::$lookUp)) {
            self::$lookUp = LookupAbstract::factory($this->db);
        }
        return self::$lookUp;
    }
}