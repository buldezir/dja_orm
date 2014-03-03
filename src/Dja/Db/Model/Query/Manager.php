<?php
/**
 * User: Alexander.Arutyunov
 * Date: 18.01.14
 * Time: 19:23
 */

namespace Dja\Db\Model\Query;

use Dja\Db\Model\Metadata;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Query;

class Manager
{
    /**
     * @var array
     */
    protected static $instances = array();

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
     */
    public function doInsert(array $data)
    {

        return $this->getQuerySet()->doInsert($data);
    }

    /**
     * @param array $data
     * @return int
     */
    public function doUpdate(array $data)
    {

        return $this->getQuerySet()->doUpdate($data);
    }

    /**
     * @return int
     */
    public function doDelete()
    {

        return $this->getQuerySet()->doDelete();
    }

    /**
     * @param int|array $arguments
     * @return \Dja\Db\Model\Model
     */
    public function get($arguments)
    {
        return $this->getQuerySet()->get($arguments);
    }

    /**
     * @param array $arguments
     * @return \Dja\Db\Model\Model
     */
    public function getOrCreate(array $arguments)
    {
        return $this->getQuerySet()->getOrCreate($arguments);
    }

    /**
     * @return QuerySet
     */
    public function all()
    {
        return $this->getQuerySet();
    }

    /**
     * @param array $arguments
     * @return QuerySet
     */
    public function selectRelated(array $arguments)
    {
        return $this->getQuerySet()->selectRelated($arguments);
    }

    /**
     * @param array $arguments
     * @return QuerySet
     */
    public function exclude(array $arguments)
    {
        return $this->getQuerySet()->exclude($arguments);
    }

    /**
     * @param array $arguments
     * @return QuerySet
     */
    public function filter(array $arguments)
    {
        return $this->getQuerySet()->filter($arguments);
    }

    /**
     * @param array $arguments
     * @return QuerySet
     */
    public function order(array $arguments)
    {
        return $this->getQuerySet()->order($arguments);
    }

    /**
     * @param $limit
     * @param null $offset
     * @return QuerySet
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