<?php
/**
 * User: Alexander.Arutyunov
 * Date: 10.07.13
 * Time: 17:47
 */

namespace Dja\Db\Model;

use Dja\Db\Model\Field\ForeignKey;
use Dja\Db\Model\Lookup\LookupAbstract;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * fluent interface
 * Class Query
 * @package Dja\Db\Model
 */
class Query implements QuerySet
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
    protected $rowDataMapping = [];

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

    protected $autoJoinMaxDepth = 3;

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
    }

    public function __toString()
    {
        if (!$this->queryStringCache) {
            return $this->buildQuery();
        } else {
            return $this->queryStringCache;
        }
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
     * $this->setRawSql('SELECT * FROM :t WHERE :pk = 1')->values() === $db->query('SELECT * FROM table WHERE long_primary_key = 1')->fetchAll()
     * @param $s
     * @return $this
     */
    public function setRawSql($s)
    {
        $this->queryStringCache = $s;
        return $this;
    }

    /**
     * @param array $data
     * @return int
     * @throws \InvalidArgumentException
     */
    public function doInsert(array $data)
    {
        if (!is_array($data)) {
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
    public function doUpdate(array $data)
    {
        if (count($this->qb->getQueryPart('where')) === 0) {
            throw new \Exception('must be WHERE conditions for update');
        }
        $this->getQueryBuilder()->update($this->qi($this->table), $this->qi('t'));
        foreach ($data as $key => $value) {
            $this->getQueryBuilder()->set($this->qi($key), $this->db->quote($this->metadata->getField($key)->dbPrepValue($value)));
        }
        return $this->getQueryBuilder()->execute();
    }

    /**
     * @return int
     * @throws \Exception
     */
    public function doDelete()
    {
        if (count($this->getQueryBuilder()->getQueryPart('where')) === 0) {
            throw new \Exception('must be WHERE conditions for delete');
        }
        $this->getQueryBuilder()->delete($this->qi($this->table), $this->qi('t'));
        return $this->getQueryBuilder()->execute();
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
        $this->getQueryBuilder()->setFirstResult($offset)->setMaxResults($limit);
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
        $this->buildJoins(); // !!!!!!!!

        $result = [];
        foreach ($arguments as $lookup => $value) {
            if (is_int($lookup)) {
                if ($value instanceof Expr || $value instanceof Query) {
                    $value = strval($value);
                }
            } else {
                // if exact lookuptype
                $lookupArr = explode('__', $lookup);
                $lookupType = end($lookupArr);
                if ($this->lookuper()->issetLookup($lookupType)) {
                    $lookupType = array_pop($lookupArr);
                } else {
                    $lookupType = 'exact';
                }

                $field = $this->findLookUpField($this->metadata, $lookupArr);

                if ($this->metadata->hasFieldObj($field)) {
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
                $origValue = $value;
                $value = $field->dbPrepLookup($value);
                list($db_column, $lookupQ, $value) = $this->lookuper()->getLookup($lookupType, $this->qi($prefix . $db_column), $value, $negate);
                if (is_array($origValue)) {
                    $value = implode(', ', $value);
                } elseif ($origValue instanceof Expr || $origValue instanceof QueryBuilder) {
                    $value = strval($value);
                } else {
                    $value = $this->db->quote($value);
                }
                $result[] = sprintf($db_column . ' ' . $lookupQ, $value);
            }
        }
        return $result;
    }

    /**
     * @param Metadata $md
     * @param array $lookupArr
     * @return Field\Base|Field\Relation
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
     * setup select columns and
     * joins part of sql query string
     */
    public function buildJoins()
    {
        if (!empty($this->joins)) {
            return;
        }
        $maxDepth = $this->autoJoinMaxDepth;
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
                /** @var ForeignKey $field */
                $relClass = $field->relationClass;
                $alias = 'j' . $i;
                $prefix = $curPrefix . $field->name . '__';
                $on = sprintf('%s = %s', $this->qi($curAlias . '.' . $field->db_column), $this->qi($alias . '.' . $field->to_field));
                foreach ($relClass::metadata()->getDbColNames() as $rCol) {
                    $columns[$alias . '.' . $rCol] = $prefix . $rCol;
                }
                $i++;
                $joinData[] = [
                    'prefix' => $prefix,
                    'selfAlias' => $curAlias,
                    'joinTable' => $relClass::metadata()->getDbTableName(),
                    'joinAlias' => $alias,
                    'on' => $on,
                    'columns' => $columns,
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
                $this->columns[$selectA] = $selectF;
            }
        }
    }

    public function populateDataMapping()
    {
        $dataMapping = [];
        foreach ($this->metadata->getLocalFields() as $field) {
            $dataMapping[] = $field->db_column;
        }
        foreach ($this->joins as $joinInfo) {
            foreach ($joinInfo['columns'] as $prefixedColName) {
                $dataMapping[] = $prefixedColName;
            }
        }
        return $dataMapping;
    }

    public function populateRow($row, $mapping)
    {
        $cls = $this->modelClassName;
        return new $cls(array_combine($mapping, $row), false);
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
                $this->getQueryBuilder()->leftJoin($this->qi($row['selfAlias']), $this->qi($row['joinTable']), $this->qi($row['joinAlias']), $row['on']);
            }
        }
        foreach ($this->metadata->getDbColNames() as $lCol) {
            $this->getQueryBuilder()->addSelect('t.' . $lCol);
        }
        foreach ($this->joins as $row) {
            foreach ($row['columns'] as $selectF => $selectA) {
                $this->getQueryBuilder()->addSelect($selectF);
            }
        }
        $this->rowDataMapping = $this->populateDataMapping();
        $this->queryStringCache = $this->getQueryBuilder()->getSQL();
        return $this->queryStringCache;
    }

    /**
     * @return Model
     */
    public function current()
    {
        if ($this->forceNoCache) {
            return $this->populateRow($this->exec()->fetch(\PDO::FETCH_NUM), $this->rowDataMapping);
        } else {
            if (!isset($this->rowCache[$this->pointer])) {
                $this->rowCache[$this->pointer] = $this->populateRow($this->exec()->fetch(\PDO::FETCH_NUM), $this->rowDataMapping);
            }
            return $this->rowCache[$this->pointer];
        }
    }

    /**
     * lazy-load execution of sql query
     * @return Statement
     */
    protected function exec()
    {
        if ($this->statement === null) {
            if (!$this->queryStringCache) {
                $this->buildQuery();
            }
            $this->statement = $this->db->query($this->queryStringCache);
            $this->rowCount = $this->statement->rowCount();
        }
        return $this->statement;
    }

    /**
     * no arguments for join all, or related field names, or single int arg = autoJoinMaxDepth
     * @return $this
     */
    public function selectRelated()
    {
        if (func_num_args() === 1 && is_array(func_get_arg(0))) {
            $args = func_get_arg(0);
        } elseif (func_num_args() === 1 && is_int(func_get_arg(0))) {
            $args = [];
            $this->autoJoinMaxDepth = func_get_arg(0);
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
    public function exclude(array $arguments)
    {
        $arguments = $this->explaneArguments($arguments, true);
        foreach ($arguments as $cond) {
            $this->getQueryBuilder()->andWhere($cond);
        }
        return $this;
    }

    /**
     * where *
     * @param $arguments
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function filter(array $arguments)
    {
        $arguments = $this->explaneArguments($arguments);
        foreach ($arguments as $cond) {
            $this->getQueryBuilder()->andWhere($cond);
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
                $this->getQueryBuilder()->addOrderBy($this->qi(substr($order, 1)), self::ORDER_DESCENDING);
            } else {
                $this->getQueryBuilder()->addOrderBy($this->qi($order), self::ORDER_ASCENDING);
            }
        }
        return $this;
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
        if (count($this->getQueryBuilder()->getQueryPart('where')) > 0 || $this->getQueryBuilder()->getMaxResults() !== null || $this->getQueryBuilder()->getFirstResult() !== null) {
            $q = clone $this;
            $q->reset('where');
            $q->reset('limit');
            $q->reset('offset');
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
        $qb = clone $this->getQueryBuilder();
        $sql = $qb->resetQueryPart('orderBy')->setMaxResults(null)->setFirstResult(null)->select('COUNT(*)')->getSQL();
        return $this->db->query($sql)->fetchColumn(0);
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
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function getQueryBuilder()
    {
        if (!$this->qb) {
            $this->qb = $this->createQueryBuilder();
        }
        return $this->qb;
    }

    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function createQueryBuilder()
    {
        return $this->db->createQueryBuilder()->from($this->qi($this->table), $this->qi('t'));
    }

    /**
     * reset custom part of query and reset sql string, statement, row cache
     * @param string $part
     * @return $this
     */
    public function reset($part)
    {
        switch ($part) {
            case 'limit':
                $this->getQueryBuilder()->setMaxResults(null);
                break;
            case 'offset':
                $this->getQueryBuilder()->setFirstResult(null);
                break;
            default:
                $this->getQueryBuilder()->resetQueryPart($part);
                break;
        }

        $this->queryStringCache = null;
//        $this->columns = [];
//        $this->joins = [];
//        $this->aliasHash = [];

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

    /**
     * @return Metadata
     */
    public function getMetadata()
    {
        return $this->metadata;
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
}