<?php

namespace Dja\Db\Model\Query;

use Dja\Db\Model\Field\Relation;
use Dja\Db\Model\Metadata;
use Dja\Db\Model\Model;
use Doctrine\DBAL\Connection;
use Traversable;

/**
 * Class Manager
 * @package Dja\Db\Model\Query
 */
class Manager implements \IteratorAggregate
{
    /**
     * @var array
     */
    protected static $instances = [];

    /**
     * @var Metadata
     */
    protected $metadata;

    /**
     * @var BaseQuerySet
     */
    protected $initialQsCache;

    /**
     * @param $modelClass
     * @return self
     */
    public static function getInstance($modelClass)
    {
        if (!isset(self::$instances[$modelClass])) {
            self::$instances[$modelClass] = new static($modelClass);
        }
        return self::$instances[$modelClass];
    }

    /**
     * @param $modelClass
     * @throws \InvalidArgumentException
     */
    public function __construct($modelClass)
    {
        $refl = new \ReflectionClass($modelClass);
        if ($refl->isAbstract()) {
            throw new \InvalidArgumentException("Cannot create objects manager for abstract class '{$modelClass}'");
        }
        $this->metadata = $modelClass::metadata();
    }

    /**
     * @return QuerySet|Traversable
     */
    public function getIterator()
    {
        return $this->getQuerySet();
    }

    /**
     * @param bool $distinct
     * @return int
     */
    public function doCount($distinct = false)
    {
        return $this->getQuerySet()->doCount($distinct);
    }

    /**
     * @param array $data
     * @return int
     * @see Queryset::doInsert
     */
    public function doInsert(array $data)
    {
        return $this->getQuerySet()->doInsert($data);
    }

    /**
     * @param int|array $arguments
     * @return Model
     */
    public function get($arguments)
    {
        return $this->getQuerySet()->get($arguments);
    }

    /**
     * @param array $arguments
     * @return Model
     */
    public function getOrCreate(array $arguments)
    {
        return $this->getQuerySet()->getOrCreate($arguments);
    }

    /**
     * @param Connection $db
     * @return QuerySet|Model[]
     */
    public function using(Connection $db)
    {
        return new QuerySet($this->metadata, null, $db);
    }

    /**
     * @return QuerySet|Model[]
     */
    public function all()
    {
        return $this->getQuerySet();
    }

    /**
     * @param array $arguments
     * @return QuerySet|Model[]
     */
    public function selectRelated(array $arguments)
    {
        return $this->getQuerySet()->selectRelated($arguments);
    }

    /**
     * @param array|QueryPart $arguments
     * @return QuerySet|Model[]
     */
    public function exclude($arguments)
    {
        return $this->getQuerySet()->exclude($arguments);
    }

    /**
     * @param array|QueryPart $arguments
     * @return QuerySet|Model[]
     */
    public function filter($arguments)
    {
        return $this->getQuerySet()->filter($arguments);
    }

    /**
     * @param array $arguments
     * @return QuerySet|Model[]
     */
    public function order(array $arguments)
    {
        return $this->getQuerySet()->order($arguments);
    }

    /**
     * @param $limit
     * @param null $offset
     * @return QuerySet|Model[]
     */
    public function limit($limit, $offset = null)
    {
        return $this->getQuerySet()->limit($limit, $offset);
    }

    /**
     * @param $sql
     * @param array $bind
     * @return RawQuerySet
     */
    public function raw($sql, array $bind = null)
    {
        return new RawQuerySet($this->metadata, $sql, $bind);
    }

    /**
     * @param array $fields
     * @return ValuesQuerySet
     */
    public function values(array $fields = null)
    {
        return new ValuesQuerySet($this->metadata, null, null, $fields);
    }

    /**
     * @param string $valueField
     * @param string|null $keyField
     * @return ValuesListQuerySet
     */
    public function valuesList($valueField, $keyField = null)
    {
        return new ValuesListQuerySet($this->metadata, null, null, $keyField, $valueField);
    }

    /**
     * @param Model $ownerModel
     * @param Relation $ownerField
     * @return RelationQuerySet
     */
    public function relation(Model $ownerModel, Relation $ownerField)
    {
        return new RelationQuerySet($this->metadata, null, null, $ownerModel, $ownerField);
    }

    /**
     * @return QuerySet
     */
    protected function getQuerySet()
    {
        if (null === $this->initialQsCache) {
            $this->initialQsCache = new QuerySet($this->metadata);
        }
        return $this->initialQsCache;
    }
}