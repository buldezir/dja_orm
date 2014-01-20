<?php
/**
 * User: Alexander.Arutyunov
 * Date: 04.01.14
 * Time: 23:01
 */

namespace Dja\Db\Model\Query;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\PhpFileCache;

/**
 * provides iteration over cached queryset
 *
 * Class QueryCache
 * @package Dja\Db\Model
 */
class QueryCache implements \Countable, \Iterator
{
    protected $query;
    protected $cache;
    protected $data = [];
    protected $i = 0;

    /**
     * @param QuerySet $q
     * @param int $lifeTime
     * @param Cache $cacheBackend
     * @return static
     */
    public static function get(QuerySet $q, $lifeTime = 3600, Cache $cacheBackend = null)
    {
        return new static($q, $lifeTime, $cacheBackend);
    }

    /**
     * @param QuerySet $q
     * @param int $lifeTime
     * @param Cache $cacheBackend
     * todo: fix for new QuerySet types
     */
    public function __construct(QuerySet $q, $lifeTime = 3600, Cache $cacheBackend = null)
    {
        $this->query = $q;
        if ($cacheBackend) {
            $this->cache = $cacheBackend;
        } else {
            $this->cache = new PhpFileCache(DJA_APP_CACHE);
        }
        $cacheKey = $q->getMetadata()->getDbTableName() . '_' . sha1(strval($q));
        if ($this->cache->contains($cacheKey)) {
            $data = $this->cache->fetch($cacheKey);
        } else {
            $data = $this->getData();
            $this->cache->save($cacheKey, $data, $lifeTime);
        }
        $className = $q->getMetadata()->getModelClass();
        foreach ($data as $rowData) {
            $this->data[] = new $className($rowData, false);
        }
    }

    /**
     * @return array
     */
    protected function getData()
    {
        $data = $this->query->values();
        foreach ($data as $i => $row) {
            $data[$i] = $this->query->mapAliasHash($row);
        }
        return $data;
    }

    /**
     * @return Model
     */
    public function current()
    {
        return $this->data[$this->i];
    }

    /**
     *
     */
    public function next()
    {
        $this->i++;
    }

    /**
     * @return int|mixed
     */
    public function key()
    {
        return $this->i;
    }

    /**
     * @return bool
     */
    public function valid()
    {
        return $this->i < count($this->data);
    }

    /**
     *
     */
    public function rewind()
    {
        $this->i = 0;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->data);
    }
}