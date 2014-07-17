<?php

namespace Dja\Db\Model\Query;

use Dja\Db\Model\Metadata;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Class ValuesListQuerySet
 * @package Dja\Db\Model\Query
 */
class ValuesListQuerySet extends ValuesQuerySet
{
    /**
     * @var string
     */
    protected $selectKeyField;

    /**
     * @var string
     */
    protected $selectValueField;

    /**
     * @var \Closure
     */
    protected $keyDataMapper;

    /**
     * @param Metadata $metadata
     * @param QueryBuilder $qb
     * @param Connection $db
     * @param string $selectKeyField
     * @param string $selectValueField
     */
    public function __construct(Metadata $metadata, QueryBuilder $qb = null, Connection $db = null, $selectKeyField = null, $selectValueField)
    {
        parent::__construct($metadata, $qb, $db);
        $this->selectKeyField = $selectKeyField;
        $this->selectValueField = $selectValueField;
    }

    /**
     * @return string
     */
    protected function buildQuery()
    {
        if (null === $this->queryStringCache) {

            $this->qb->resetQueryPart('select')->resetQueryPart('join');

            foreach ($this->joinMap as $joinData) {
                $this->qb->leftJoin($this->qi($joinData['selfAlias']), $this->qi($joinData['joinTable']), $this->qi($joinData['joinAlias']), $joinData['on']);
            }

            if (false !== $this->selectKeyField) {
                if (null === $this->selectKeyField) {
                    $selectKeyField = $this->metadata->getField('pk');
                } else {
                    $selectKeyField = $this->metadata->findField($this->selectKeyField);
                }
                if (isset($this->relatedSelectCols[$this->selectKeyField])) {
                    $this->qb->addSelect($this->qi($this->relatedSelectCols[$this->selectKeyField]));
                } else {
                    $this->qb->addSelect($this->qi('t.' . $selectKeyField->db_column));
                }
                $this->keyDataMapper = function ($row) use ($selectKeyField) {
                    return $selectKeyField->fromDbValue($row[0]);
                };
            } else {
                $this->keyDataMapper = null;
            }

            $selectValueField = $this->metadata->findField($this->selectValueField);
            if (isset($this->relatedSelectCols[$this->selectValueField])) {
                $this->qb->addSelect($this->relatedSelectCols[$this->selectValueField]);
            } else {
                $this->qb->addSelect($this->qi('t.' . $selectValueField->db_column));
            }
            $this->rowDataMapper = function ($row) use ($selectValueField) {
                return $selectValueField->fromDbValue($row[1]);
            };

            $this->queryStringCache = $this->qb->getSQL();
        }
        return $this->queryStringCache;
    }

    /**
     * @return int|mixed
     */
    public function key()
    {
        if (null !== $this->keyDataMapper) {
            $mapper = $this->keyDataMapper;
            return $mapper($this->currentFetchedRow);
        } else {
            return parent::key();
        }
    }
}