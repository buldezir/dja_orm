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
     * @var array
     */
    protected $bindValues = [];

    /**
     * @param Metadata $metadata
     * @param $query
     * @param array $bind
     * @param Connection $db
     */
    public function __construct(Metadata $metadata, $query, array $bind = null, Connection $db = null)
    {
        $this->metadata = $metadata;
        if (null !== $db) {
            $this->db = $db;
        } else {
            $this->db = $metadata->getDbConnection();
        }

        $selectAllFields = [];
        $dbCols = $this->metadata->getDbColNames();
        foreach ($dbCols as $colName) {
            $selectAllFields[] = $this->qi($colName);
        }

        $query = str_ireplace([
            'select *',
            ':t',
        ], [
            'SELECT ' . implode(', ', $selectAllFields),
            $this->qi($this->metadata->getDbTableName()),
        ], $query);

        $query = preg_replace_callback('/:(\w+)/', function ($matches) use ($metadata) {
            if ($metadata->__isset($matches[1])) {
                return $this->qi($metadata->getField($matches[1])->db_column);
            } else {
                return $matches[0];
            }
        }, $query);

        $this->queryStringCache = $query;


        if (null !== $bind) {
            $this->bind($bind);
        }
        $this->returnObjects();
    }

    /**
     * @param array $bind
     * @return $this
     */
    public function bind(array $bind = null)
    {
        foreach ($bind as $k => $v) {
            $this->bindValues[$k] = $v;
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function returnObjects()
    {
        $cls = $this->metadata->getModelClass();
        $dbColsAsKeys = array_flip($this->metadata->getDbColNames());
        $this->rowDataMapper = function ($row) use ($cls, $dbColsAsKeys) {
            $row = array_intersect_key($row, $dbColsAsKeys);
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

    protected function execute()
    {
        if ($this->currentStatement === null) {
            $this->currentStatement = $this->db->prepare($this->buildQuery());
            $this->currentStatement->execute($this->bindValues);
            $this->rowCount = $this->currentStatement->rowCount();
        }
        return $this->currentStatement;
    }


    ##############################################################################################################

    /*public function selectRelated(array $arguments)
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
    }*/
}