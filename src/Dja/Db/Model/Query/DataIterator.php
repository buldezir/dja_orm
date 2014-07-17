<?php

namespace Dja\Db\Model\Query;

use Dja\Db\Model\Model;

/**
 * just abstraction for avoid duplicate code
 *
 * Class DataIterator
 * @package Dja\Db\Model\Query
 */
abstract class DataIterator implements \Countable, \Iterator
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $db;

    /**
     * @var \Doctrine\DBAL\Statement
     */
    protected $currentStatement;

    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var int
     */
    protected $rowCount = 0;

    /**
     * @var array
     */
    protected $currentFetchedRow;

    /**
     * @var int
     */
    protected $internalPointer = 0;

    /**
     * @var string
     */
    protected $queryStringCache;

    /**
     * @var \Closure
     */
    protected $rowDataMapper;

    /**
     * @var int
     */
    protected $fetchType = \PDO::FETCH_NUM;

    /**
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->buildQuery();
        } catch (\Exception $e) {
            echo pr(strval($e));
            return '';
        }
    }

    /**
     * fetches all rows and stores them in array
     * @return \Dja\Db\Model\Model[]
     */
    public function cached()
    {
        if (empty($this->data)) {
            foreach ($this as $i => $row) {
                $this->data[$i] = $row;
            }
        }
        return $this->data;
    }

    /**
     * @return Model|mixed
     */
    public function current()
    {
        if ($this->currentFetchedRow === null) {
            $this->currentFetchedRow = $this->execute()->fetch($this->fetchType);
        }
        if (false === $this->currentFetchedRow) {
            return null;
        } else {
            $mapper = $this->rowDataMapper;
            return $mapper($this->currentFetchedRow);
        }
    }

    /**
     * ++
     */
    public function next()
    {
        $this->internalPointer++;
        $this->currentFetchedRow = null;
    }

    /**
     * @return int|mixed
     */
    public function key()
    {
        return $this->internalPointer;
    }

    /**
     * @return bool
     */
    public function valid()
    {
        $this->execute();
        return $this->internalPointer < $this->rowCount;
    }

    /**
     * start new iteration
     */
    public function rewind()
    {
        if ($this->internalPointer > 0) {
            $this->resetStatement();
        }
    }

    /**
     * @return int
     */
    public function count()
    {
        $this->execute();
        return $this->rowCount;
    }

    /**
     * alias for $this->db->quoteIdentifier
     * @param $v
     * @return string
     */
    protected function qi($v)
    {
        return $this->db->quoteIdentifier($v);
    }

    /**
     * alias for $this->db->quote
     * @param $v
     * @param null $type
     * @return string
     */
    protected function qv($v, $type = null)
    {
        return $this->db->quote($v, $type);
    }

    /**
     * @return $this
     */
    protected function resetStatement()
    {
        $this->internalPointer = 0;
        $this->currentStatement = null;
        $this->currentFetchedRow = null;
        $this->data = [];
        return $this;
    }

    protected function execute()
    {
        if ($this->currentStatement === null) {
            $this->currentStatement = $this->db->query($this->buildQuery());
            $this->rowCount = $this->currentStatement->rowCount();
        }
        return $this->currentStatement;
    }

    /**
     * @return string
     */
    abstract protected function buildQuery();
}