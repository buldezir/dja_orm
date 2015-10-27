<?php

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
            $selectAllFields[] = 't.' . $this->qi($colName);
        }

        $query = str_ireplace([
            'select *',
            ':t',
        ], [
            'SELECT ' . implode(', ', $selectAllFields),
            $this->qi($this->metadata->getDbTableName()) . ' t',
        ], $query);

        // auto replace :field_name placeholders by their quoted db column names
        $query = preg_replace_callback('/:(\w+)/', function ($matches) use ($metadata) {
            if ($metadata->__isset($matches[1])) {
                return $this->qi($metadata->getField($matches[1])->db_column);
            } else {
                return $matches[0];
            }
        }, $query);

        $this->queryStringCache = $query;

        // other :somename placeholders treated as prepared statement bindings
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
     * @param bool|true $safe
     * @return $this
     */
    public function returnObjects($safe = true)
    {
        $cls = $this->metadata->getModelClass();
        $dbColsAsKeys = array_flip($this->metadata->getDbColNames());
        $this->rowDataMapper = function ($row) use ($cls, $dbColsAsKeys, $safe) {
            if ($safe) {
                return new $cls(array_intersect_key($row, $dbColsAsKeys), false);
            } else {
                return new $cls($row, false);
            }
        };
        return $this;
    }

    /**
     * @return $this
     */
    public function returnValues()
    {
        $this->rowDataMapper = function ($row) {
            return $row;
        };
        return $this;
    }

    /**
     * @param Connection $db
     * @return RawQuerySet
     */
    public function using(Connection $db)
    {
//        $this->db = $db;
//        return $this;
        $copy = clone $this;
        $copy->db = $db;
        return $copy;
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