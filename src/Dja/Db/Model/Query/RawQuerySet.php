<?php
/**
 * User: Alexander.Arutyunov
 * Date: 14.01.14
 * Time: 17:01
 */

namespace Dja\Db\Model\Query;

use Dja\Db\Model\Metadata;
use Doctrine\DBAL\Connection;

/**
 * Class RawQuerySet
 * @package Dja\Db\Model\Query
 */
class RawQuerySet extends DataIterator
{
    /**
     * @var Metadata
     */
    protected $metadata;

    /**
     * @var int
     */
    protected $fetchType = \PDO::FETCH_ASSOC;

    /**
     * @param Metadata $metadata
     * @param $query
     * @param Connection $db
     */
    public function __construct(Metadata $metadata, $query, Connection $db = null)
    {
        $this->metadata = $metadata;
        if (null !== $db) {
            $this->db = $db;
        } else {
            $this->db = $metadata->getDbConnection();
        }
        $this->queryStringCache = str_replace([
            ':t',
            ':pk',
        ], [
            $this->qi($this->metadata->getDbTableName()),
            $this->qi($this->metadata->getPrimaryKey()),
        ], $query);

        $this->returnObjects();
    }

    /**
     * @return $this
     */
    public function returnObjects()
    {
        $cls = $this->metadata->getModelClass();
        $this->rowDataMapper = function ($row) use ($cls) {
            return new $cls($row, false);
        };
        return $this;
    }

    /**
     * @return $this
     */
    public function returnValues()
    {
        $this->rowDataMapper = function (&$row) {
            return $row;
        };
        return $this;
    }

    /**
     * @return mixed|string
     */
    protected function buildQuery()
    {
        return $this->queryStringCache;
    }

    ##############################################################################################################

    public function selectRelated(array $arguments)
    {
        throw new \BadMethodCallException('Cannot use '.__METHOD__.' for raw querySet');
    }

    public function exclude(array $arguments)
    {
        throw new \BadMethodCallException('Cannot use '.__METHOD__.' for raw querySet');
    }

    public function filter(array $arguments)
    {
        throw new \BadMethodCallException('Cannot use '.__METHOD__.' for raw querySet');
    }

    public function order(array $arguments)
    {
        throw new \BadMethodCallException('Cannot use '.__METHOD__.' for raw querySet');
    }

    public function limit($limit, $offset = null)
    {
        throw new \BadMethodCallException('Cannot use '.__METHOD__.' for raw querySet');
    }

    public function doCount($distinct = false)
    {
        throw new \BadMethodCallException('Cannot use '.__METHOD__.' for raw querySet');
    }
}