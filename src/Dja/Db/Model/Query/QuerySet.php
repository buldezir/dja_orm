<?php
/**
 * User: Alexander.Arutyunov
 * Date: 15.01.14
 * Time: 14:07
 */

namespace Dja\Db\Model\Query;

/**
 * Class QuerySet
 * @package Dja\Db\Model\Query
 */
class QuerySet extends BaseQuerySet
{
    /**
     * @param array $data
     * @return int
     * @throws \InvalidArgumentException
     */
    public function doInsert(array $data)
    {
        $dataReady = [];
        foreach ($data as $key => $value) {
            $dataReady[$this->qi($key)] = $this->qv($this->metadata->getField($key)->dbPrepValue($value));
        }
        $this->db->insert($this->qi($this->table), $dataReady);
        return $this->db->lastInsertId();
    }

    /**
     * @param array $data
     * @return int
     * @throws \LogicException
     */
    public function doUpdate(array $data)
    {
        if (count($this->qb->getQueryPart('where')) === 0) {
            throw new \LogicException('must be WHERE conditions for update');
        }
        $this->qb->update($this->qi($this->table), $this->qi('t'));
        foreach ($data as $key => $value) {
            $this->qb->set($this->qi($key), $this->db->quote($this->metadata->getField($key)->dbPrepValue($value)));
        }
        return $this->qb->execute();
    }

    /**
     * @return int
     * @throws \LogicException
     */
    public function doDelete()
    {
        if (count($this->qb->getQueryPart('where')) === 0) {
            throw new \LogicException('must be WHERE conditions for delete');
        }
        $this->qb->delete($this->qi($this->table), $this->qi('t'));
        return $this->qb->execute();
    }

    /**
     * @param $sql
     * @param array $bind
     * @return RawQuerySet
     */
    public function raw($sql, array $bind = null)
    {
        return new RawQuerySet($this->metadata, $sql, $bind, $this->db);
    }

    /**
     * @param array $fields
     * @return ValuesQuerySet
     */
    public function values(array $fields = null)
    {
        $qs = new ValuesQuerySet($this->metadata, $this->qb, $this->db, $fields);
        if ($this->joinMaxDepth) {
            $qs->_setJoinDepth($this->joinMaxDepth);
        } elseif (!empty($this->relatedSelectFields)) {
            $qs->_addRelatedSelectFields($this->relatedSelectFields);
        }
        return $qs;
    }

    /**
     * @param string $valueField
     * @param string|null $keyField
     * @return ValuesListQuerySet
     */
    public function valuesList($valueField, $keyField = null)
    {
        $qs = new ValuesListQuerySet($this->metadata, $this->qb, $this->db, $keyField, $valueField);
        if ($this->joinMaxDepth) {
            $qs->_setJoinDepth($this->joinMaxDepth);
        } elseif (!empty($this->relatedSelectFields)) {
            $qs->_addRelatedSelectFields($this->relatedSelectFields);
        }
        return $qs;
    }

    /**
     * @return string
     */
    protected function buildQuery()
    {
        if (null === $this->queryStringCache) {

            $this->qb->resetQueryPart('select')->resetQueryPart('join');

            $dataMapping = [];
            foreach ($this->joinMap as $joinData) {
                $this->qb->leftJoin($this->qi($joinData['selfAlias']), $this->qi($joinData['joinTable']), $this->qi($joinData['joinAlias']), $joinData['on']);
            }
            foreach ($this->metadata->getDbColNames() as $lCol) {
                $this->qb->addSelect('t.' . $lCol);
                $dataMapping[] = $lCol;
            }
            foreach ($this->relatedSelectCols as $underscoreName => $selectAlias) {
                $this->qb->addSelect($selectAlias);
                $dataMapping[] = $underscoreName;
            }
            $cls = $this->metadata->getModelClass();
            $this->rowDataMapper = function ($row) use ($cls, $dataMapping) {
                return new $cls(array_combine($dataMapping, $row), false);
            };
            $this->queryStringCache = $this->qb->getSQL();
        }
        return $this->queryStringCache;
    }
}