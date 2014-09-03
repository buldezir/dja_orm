<?php

namespace Dja\Db\Model\Query;

use Dja\Db\Model\Metadata;
use Dja\Db\Model\Model;
use Doctrine\DBAL\Connection;

/**
 * Class ValuesQuerySet
 * @package Dja\Db\Model\Query
 */
class ValuesQuerySet extends BaseQuerySet
{
    /**
     * @var array|null
     */
    protected $selectFields;

    /**
     * @param Metadata $metadata
     * @param QueryBuilder $qb
     * @param Connection $db
     * @param array $fields
     */
    public function __construct(Metadata $metadata, QueryBuilder $qb = null, Connection $db = null, array $fields = null)
    {
        parent::__construct($metadata, $qb, $db);
        $this->selectFields = $fields;
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
            if (null === $this->selectFields) {
                foreach ($this->metadata->getDbColNames() as $lCol) {
                    $this->qb->addSelect($this->qi('t.' . $lCol));
                    $dataMapping[] = $lCol;
                }
                foreach ($this->relatedSelectCols as $underscoreName => $selectAlias) {
                    $this->qb->addSelect($this->qi($selectAlias));
                    $dataMapping[] = $underscoreName;
                }
            } else {
                foreach ($this->selectFields as $underscoreName) {
                    $selectField = $this->metadata->findField($underscoreName);
                    if (isset($this->relatedSelectCols[$underscoreName])) {
                        $this->qb->addSelect($this->qi($this->relatedSelectCols[$underscoreName]));
                        $dataMapping[] = $underscoreName;
                    } else {
                        $this->qb->addSelect($this->qi('t.' . $selectField->db_column));
                        $dataMapping[] = $selectField->db_column;
                    }
                }

            }
            $this->rowDataMapper = function ($row) use ($dataMapping) {
                return array_combine($dataMapping, $row);
            };
            $this->queryStringCache = $this->qb->getSQL();
        }
        return $this->queryStringCache;
    }

    /**
     * @param array $arguments
     * @return Model|void
     * @throws \BadMethodCallException
     */
    public function getOrCreate(array $arguments)
    {
        throw new \BadMethodCallException('Cant use getOrCreate method for ' . get_class($this));
    }
}