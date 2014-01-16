<?php
/**
 * User: Alexander.Arutyunov
 * Date: 15.01.14
 * Time: 14:07
 */

namespace Dja\Db\Model\Query;

class QuerySet extends BaseQuerySet
{
    /**
     * @return ValuesQuerySet
     */
    public function values()
    {
        $qs = new ValuesQuerySet($this->metadata, $this->qb, $this->db);
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
            foreach ($this->joinMap as $row) {
                foreach ($row['columns'] as $selectF => $selectA) {
                    $this->qb->addSelect($selectF);
                    $dataMapping[] = $selectA;
                }
            }
            $cls = $this->modelClassName;
            $this->rowDataMapper = function ($row) use ($cls, $dataMapping) {
                return new $cls(array_combine($dataMapping, $row), false);
            };
            $this->queryStringCache = $this->qb->getSQL();
        }
        return $this->queryStringCache;
    }
}