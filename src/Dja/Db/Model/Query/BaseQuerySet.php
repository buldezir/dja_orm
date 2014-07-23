<?php

namespace Dja\Db\Model\Query;

use Dja\Db\Model\Metadata;
use Dja\Db\Model\Model;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Class BaseQuerySet
 * @package Dja\Db\Model\Query
 */
abstract class BaseQuerySet extends DataIterator implements \ArrayAccess
{
    /**
     * @var Metadata
     */
    protected $metadata;

    /**
     * @var \Doctrine\DBAL\Query\QueryBuilder
     */
    protected $qb;

    /**
     * @var \Dja\Db\Model\Lookup\LookupAbstract
     */
    protected $lookuper;

    /**
     * @var int
     */
    protected $joinMaxDepth;

    /**
     * @var array
     */
    protected $joinMap = [];

    /**
     * @var array
     */
    protected $relatedSelectCols = [];

    /**
     * @var array
     */
    protected $relatedSelectFields = [];


    /**
     * @param Metadata $metadata
     * @param QueryBuilder $qb
     * @param Connection $db
     */
    public function __construct(Metadata $metadata, QueryBuilder $qb = null, Connection $db = null)
    {
        $this->metadata = $metadata;
        if (null !== $db) {
            $this->db = $db;
        } else {
            $this->db = $metadata->getDbConnection();
        }
        if (null !== $qb) {
            $this->qb = $qb;
        } else {
            $this->qb = $this->db->createQueryBuilder()->from($this->qi($metadata->getDbTableName()), $this->qi('t'));
        }
        $this->lookuper = \Dja\Db\Model\Lookup\LookupAbstract::factory($this->db);
    }

    /**
     * need to make clone of query builder
     */
    public function __clone()
    {
        $this->qb = clone $this->qb;
        $this->resetStatement();
    }

    ####################################################################################

    /**
     * @param Connection $db
     * @throws \Exception
     */
    public function using(Connection $db = null)
    {
        throw new \Exception('Not implemented');
//        $qs = new static($this->metadata, null, $db);
//        return $qs->importMethodCalls($this->methodCalls);
    }

    /**
     * joinMaxDepth -> selectRelated(['depth' => 5])
     * filter -> selectRelated(['field_name1', 'field_name2'])
     * no arguments for join all, or related field names, or single int arg = joinMaxDepth
     * @param array $arguments
     * @return BaseQuerySet|Model[]
     */
    public function selectRelated(array $arguments)
    {
        $copy = clone $this;
        if (isset($arguments['depth'])) {
            $copy->_setJoinDepth((int)$arguments['depth']);
        } else {
            $copy->_addRelatedSelectFields($arguments);
        }
        return $copy;
    }

    /**
     * where NOT *
     * @param array|QueryPart $arguments
     * @return BaseQuerySet|Model[]
     * @throws \InvalidArgumentException
     */
    public function exclude($arguments)
    {
        return $this->applyFilterLogic($arguments, true);
    }

    /**
     * where *
     * @param array|QueryPart $arguments
     * @return BaseQuerySet|Model[]
     * @throws \InvalidArgumentException
     */
    public function filter($arguments)
    {
        return $this->applyFilterLogic($arguments);
    }

    /**
     * set Order By part for query
     * @param array $arguments
     * @throws \DomainException
     * @return BaseQuerySet|Model[]
     */
    public function order(array $arguments)
    {
        $copy = clone $this;
        $copy->_qb()->resetQueryPart('orderBy');
        foreach ($arguments as $order) {
            if ($order{0} === '-') {
                $fName = substr($order, 1);
                $orderDir = 'DESC';
            } else {
                $fName = $order;
                $orderDir = 'ASC';
            }
            $field = $this->metadata->findField($fName);
            if ($this->metadata->hasFieldObj($field)) {
                $col = 't.' . $field->db_column;
            } else {
                if (!isset($this->relatedSelectCols[$fName])) {
                    throw new \DomainException("Cant lookup for related field '{$fName}' without selectRelated()");
                }
                $col = $this->relatedSelectCols[$fName];
            }
            $copy->_qb()->addOrderBy($this->qi($col), $orderDir);
        }
        return $copy;
    }

    /**
     * @param $limit
     * @param null $offset
     * @return BaseQuerySet|Model[]
     */
    public function limit($limit, $offset = null)
    {
        $copy = clone $this;
        $copy->_qb()->setFirstResult($offset)->setMaxResults($limit);
        return $copy;
    }

    /**
     * @param int $curPage
     * @param int $itemsPerPage
     * @return BaseQuerySet|Model[]
     */
    public function byPage($curPage, $itemsPerPage = 50)
    {
        $offset = ($curPage - 1) * $itemsPerPage;
        return $this->limit($itemsPerPage, $offset);
    }

    ####################################################################################

    /**
     * make count query with current filters
     * @param bool $distinct
     * @return int
     */
    public function doCount($distinct = false)
    {
        if ($distinct) {
            $column = $this->qi('t.' . $this->metadata->getPrimaryKey());
            $c = "COUNT(DISTINCT $column)";
        } else {
            $c = 'COUNT(*)';
        }
        $qb = clone $this->qb;
        $sql = $qb->resetQueryPart('orderBy')->setMaxResults(null)->setFirstResult(null)->select($c)->getSQL();
        return $this->db->query($sql)->fetchColumn(0);
    }

    /**
     * @param $arguments
     * @return Model
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function get($arguments)
    {
        if (is_int($arguments)) {
            $pk = $this->metadata->getPrimaryKey();
            $arguments = [$pk => intval($arguments)];
        } elseif (!is_array($arguments)) {
            throw new \InvalidArgumentException('$arguments must be int or array');
        }
        $result = $this->filter($arguments)->current();
        if (!$result) {
            throw new \RuntimeException('Not found');
        }
        return $result;
    }

    /**
     * @param array $arguments
     * @return Model
     */
    public function getOrCreate(array $arguments)
    {
        try {
            return $this->get($arguments);
        } catch (\RuntimeException $e) {
            $class = $this->metadata->getModelClass();
            return new $class($arguments);
        }
    }

    /**
     * magic
     * [offset:limit] -> BaseQuerySet
     * [offset] -> [offset:1]->current()
     * @param mixed $key
     * @throws \InvalidArgumentException
     * @return $this|Model|Model[]
     */
    public function offsetGet($key)
    {
        if (strpos($key, ':') !== false) {
            list($offset, $limit) = explode(':', $key);
            return $this->limit(intval($limit), intval($offset));
        } elseif (is_int($key)) {
            return $this->limit(1, $key)->current();
        } else {
            throw new \InvalidArgumentException('Valid arguments for method ' . __METHOD__ . ' are "int:int", int');
        }
    }

    ####################################################################################

    /**
     * setup select columns and
     * joins part of sql query string
     * @param array|null $fieldFilter
     */
    protected function buildJoinMap(array $fieldFilter = null)
    {
        $maxDepth = $this->joinMaxDepth;
        $processRelations = function ($md, $curPrefix = '', $curAlias = 't', $curDepth = 0) use (&$processRelations, $maxDepth, $fieldFilter) {
            /** @var Metadata $md */
            static $i = 1;
            if (null === $fieldFilter && $curDepth > $maxDepth) {
                //echo 'join depth err. ';
                return [];
            }
            $relFields = $md->getRelationFields();
            $joinData = [];
            foreach ($relFields as $field) {
                if (!$field->canAutoJoin()) {
                    continue;
                }
                if (null !== $fieldFilter && !in_array($curPrefix . $field->name, $fieldFilter)) {
                    continue;
                }
                $columns = [];
                /** @var \Dja\Db\Model\Field\Relation $field */
                /** @var \Dja\Db\Model\Model $relClass */
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

        $this->joinMap = $processRelations($this->metadata);
        $this->relatedSelectCols = [];

        foreach ($this->joinMap as $row) {
            foreach ($row['columns'] as $selectAlias => $underscoreName) {
                $this->relatedSelectCols[$underscoreName] = $selectAlias;
            }
        }
    }

    /**
     * DOES NOT support nested expressions !!!
     * @param array|QueryPart $arguments
     * @param bool $negate
     * @return BaseQuerySet
     * @throws \InvalidArgumentException
     */
    protected function applyFilterLogic($arguments, $negate = false)
    {
        if (is_array($arguments)) {
            $arguments = QueryPart::_AND($arguments);
        } elseif (!$arguments instanceof QueryPart) {
            throw new \InvalidArgumentException("arguments must be array or instance of QueryPart");
        }
        $argumentsArray = $this->explaneArguments($arguments->getArguments(), $negate);
        $copy = clone $this;
        $expr = new CompositeExpression($arguments->getType(), $argumentsArray);
        switch ($arguments->getType()) {
            case QueryPart::TYPE_AND:
                $copy->_qb()->andWhere($expr);
                break;
            case QueryPart::TYPE_OR:
                $copy->_qb()->orWhere($expr);
                break;
            default:
                break;
        }
        return $copy;
    }

    /**
     * array('is_active__exact' => 1, 'is_superuser__exact' => F('is_staff'))
     * array('pub_date__lte' => '2006-01-01')
     * array('user__is_active' => true)
     * @param array $arguments
     * @param bool $negate
     * @return array
     */
    protected function explaneArguments(array $arguments, $negate = false)
    {
        $result = [];
        foreach ($arguments as $lookup => $value) {
            /** @var \Dja\Db\Model\Field\Base $field */
            list($field, $lookupType, $column) = $this->explainLookup($lookup);
//            $origValue = $value;
//            $value = $field->dbPrepLookup($value);
            list($db_column, $lookupQ, $value) = $this->lookuper->getLookup($lookupType, $this->qi($column), $value, $negate);
            if ($value instanceof \Dja\Db\Model\Expr || $value instanceof QueryBuilder) {
                $value = strval($value);
            } elseif ($value instanceof QuerySet) {
                $value = strval($value->valuesList('pk', false));
            } elseif ($value instanceof ValuesListQuerySet) {
            } else {
                $value = $this->db->quote($value);
            }
            $result[] = sprintf($db_column . ' ' . $lookupQ, $value);
        }
        return $result;
    }

    /**
     * [$field, $lookupType, $column]
     * @param string $lookup
     * @return array
     * @throws \Exception
     */
    protected function explainLookup($lookup)
    {
        $lookupArr = explode('__', $lookup);
        $lookupType = end($lookupArr);
        if ($this->lookuper->issetLookup($lookupType)) {
            $lookupType = array_pop($lookupArr);
        } else {
            $lookupType = 'exact';
        }
        $lookup = implode('__', $lookupArr);
        $field = $this->metadata->findField($lookup);

        if ($this->metadata->hasFieldObj($field)) {
            return [$field, $lookupType, 't.' . $field->db_column];
        } else {
            if (!isset($this->relatedSelectCols[$lookup])) {
                throw new \DomainException("Cant lookup for related field '{$lookup}' without selectRelated()");
            }
            return [$field, $lookupType, $this->relatedSelectCols[$lookup]];
        }
    }

    ####################################################################################

    /**
     * @return $this
     */
    protected function resetStatement()
    {
        parent::resetStatement();
        $this->queryStringCache = null;
        return $this;
    }

    public function _qb()
    {
        return $this->qb;
    }

    /**
     * @param array $fields
     */
    public function _addRelatedSelectFields(array $fields)
    {
        foreach ($fields as $f) {
            $this->relatedSelectFields[] = $f;
        }
        $this->buildJoinMap($this->relatedSelectFields);
    }

    /**
     * @param $value
     * @throws \LogicException
     */
    public function _setJoinDepth($value)
    {
        if ($value < $this->joinMaxDepth) {
            throw new \InvalidArgumentException("Cannot decrement join depth. current={$this->joinMaxDepth}.");
        }
        $this->joinMaxDepth = $value;
        $this->buildJoinMap();
    }

    ####################################################################################

    public function offsetExists($offset)
    {
        throw new \BadMethodCallException('Cannot use ' . __METHOD__ . ' for querySet');
    }

    public function offsetSet($offset, $value)
    {
        throw new \BadMethodCallException('Cannot use ' . __METHOD__ . ' for querySet');
    }

    public function offsetUnset($offset)
    {
        throw new \BadMethodCallException('Cannot use ' . __METHOD__ . ' for querySet');
    }
}