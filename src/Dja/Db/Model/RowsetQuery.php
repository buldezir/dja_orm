<?php
/**
 * User: Alexander.Arutyunov
 * Date: 15.11.13
 * Time: 10:44
 */

namespace Dja\Db\Model;

/**
 * ultra awesome iterator over biiiiig queries, fetching them in parts
 * Class RowsetQuery
 * @package Dja\Db\Model
 */
class RowsetQuery extends Query
{
    protected $baseStart;

    protected $baseLimit;

    protected $currentStart = 0;

    protected $currentRowPos = 0;

    protected $rowsPerIter = 1000;

    /**
     * @param $n
     * @return $this
     */
    public function rowsetLimit($n)
    {
        $this->rowsPerIter = $n;
        return $this;
    }

    public function current()
    {
        if ($this->forceNoCache) {
            $cls = $this->modelClassName;
            return new $cls($this->exec()->fetch(), false, true);
        } else {
            $cls = $this->modelClassName;
            return new $cls($this->exec()->fetch(), false);
        }
    }

    public function next()
    {
        $this->currentRowPos++;
        if ($this->currentRowPos >= $this->rowsPerIter) {
            $this->nextRowset();
        }
        parent::next();
    }

    public function valid()
    {
        return ($this->currentRowPos < $this->rowCount && $this->validRowset());
    }

    public function rewind()
    {
        $this->rewindRowset();
        parent::rewind();
    }

    protected function rewindRowset()
    {
        if (null === $this->baseStart) {
            $this->baseStart = (int) $this->qb->getFirstResult();
        } else {
            $this->qb->setFirstResult($this->baseStart);
            $this->statement = null;
            $this->currentRowPos = 0;
        }
        if (null === $this->baseLimit) {
            $this->baseLimit = $this->qb->getMaxResults();
        }
        $this->currentStart = $this->baseStart;
        $this->qb->setMaxResults($this->rowsPerIter);
        if (!$this->queryStringCache) {
            $this->buildQuery();
        }
    }

    protected function nextRowset()
    {
        $this->currentStart += $this->rowsPerIter;
        $this->qb->setFirstResult($this->currentStart);
        $this->statement = null;
        $this->currentRowPos = 0;
        $this->exec();
    }

    protected function validRowset()
    {
        return ($this->pointer < $this->baseLimit);
    }

    protected function exec()
    {
        if ($this->statement === null) {
            $this->statement = $this->qb->execute();
            $this->rowCount = $this->statement->rowCount();
            //dump('Exec [' . $this->queryStringCache . ']');
            //pr('Exec [' . $this->qb->getSQL() . ']');
        }
        return $this->statement;
    }
}