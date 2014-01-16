<?php
/**
 * User: Alexander.Arutyunov
 * Date: 14.01.14
 * Time: 17:06
 */

namespace Dja\Db\Model\Query;

use Dja\Db\Model\Metadata;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

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

            if (null === $this->selectKeyField) {
                $selectKeyField = $this->metadata->getField('pk');
            } else {
                $selectKeyField = $this->findLookUpField($this->metadata, explode('__', $this->selectKeyField));
            }
            $selectValueField = $this->findLookUpField($this->metadata, explode('__', $this->selectValueField));

            if (isset($this->relatedSelectCols[$this->selectKeyField])) {
                $this->qb->addSelect($this->relatedSelectCols[$this->selectKeyField]);
            } else {
                $this->qb->addSelect('t.' . $selectKeyField->db_column);
            }
            if (isset($this->relatedSelectCols[$this->selectValueField])) {
                $this->qb->addSelect($this->relatedSelectCols[$this->selectValueField]);
            } else {
                $this->qb->addSelect('t.' . $selectValueField->db_column);
            }

            $this->rowDataMapper = function ($row) use ($selectValueField) {
                return $selectValueField->fromDbValue($row[1]);
            };

            $this->keyDataMapper = function ($row) use ($selectKeyField) {
                return $selectKeyField->fromDbValue($row[0]);
            };

            $this->queryStringCache = $this->qb->getSQL();
        }
        return $this->queryStringCache;
    }

    public function key()
    {
        $mapper = $this->keyDataMapper;
        return $mapper($this->currentFetchedRow);
    }
}