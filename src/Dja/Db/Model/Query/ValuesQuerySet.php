<?php
/**
 * User: Alexander.Arutyunov
 * Date: 14.01.14
 * Time: 17:03
 */

namespace Dja\Db\Model\Query;

class ValuesQuerySet extends BaseQuerySet
{
	/**
     * @var array
     */
    protected $selectFields = [];

	/**
     * @param Metadata $metadata
     * @param QueryBuilder $qb
     * @param Connection $db
     * @param string $selectKeyField
     * @param string $selectValueField
     */
    public function __construct(Metadata $metadata, QueryBuilder $qb = null, Connection $db = null, array $fields = null)
    {
        parent::__construct($metadata, $qb, $db);
        
		if (null === $fields) {
			
		}
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
            $this->rowDataMapper = function ($row) use ($dataMapping) {
                return array_combine($dataMapping, $row);
            };
            $this->queryStringCache = $this->qb->getSQL();
        }
        return $this->queryStringCache;
    }
}