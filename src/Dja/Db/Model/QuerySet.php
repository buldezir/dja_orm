<?php
/**
 * User: Alexander.Arutyunov
 * Date: 13.01.14
 * Time: 16:42
 */

namespace Dja\Db\Model;

/**
 * Class QuerySet
 * @package Dja\Db\Model
 */
interface QuerySet extends \Countable, \Iterator
{
    /**
     * @return string
     */
    public function __toString();

    /**
     * @param string $s
     * @return $this
     */
    public function setRawSql($s);

    /**
     * @param array $a
     * @return $this
     */
    public function setRelation(array $a);

    /**
     * @return $this
     */
    public function noCache();

    /**
     * @return $this
     */
    public function selectRelated();

    /**
     * @param $part
     * @return $this
     */
    public function reset($part);


    /**
     * @param array $data
     * @return int
     */
    public function doInsert(array $data);

    /**
     * @param array $data
     * @return int
     */
    public function doUpdate(array $data);

    /**
     * @return int
     */
    public function doDelete();


    /**
     * @return $this
     */
    public function all();

    /**
     * @param array $arguments
     * @return $this
     */
    public function filter(array $arguments);

    /**
     * @param array $arguments
     * @return $this
     */
    public function exclude(array $arguments);

    /**
     * @param int $limit
     * @param null|int $offset
     * @return $this
     */
    public function limit($limit, $offset = null);

    /**
     * @return $this
     */
    public function order();


    /**
     * @param $value
     * @return Model
     */
    public function get($value);

    /**
     * @param bool $applyDataFilter
     * @return array
     */
    public function values($applyDataFilter = false);

    /**
     * @param $keyField
     * @param $valueField
     * @param bool $applyDataFilter
     * @return array
     */
    public function valuesDict($keyField, $valueField, $applyDataFilter = false);

    /**
     * @return int
     */
    public function doCount();

    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function createQueryBuilder();

    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function getQueryBuilder();

    /**
     * @return Metadata
     */
    public function getMetadata();
}