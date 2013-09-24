<?php
/**
 * User: Alexander.Arutyunov
 * Date: 22.08.13
 * Time: 11:02
 */

namespace Dja\Db\Driver\Mysqli;

use Dja\Db\Driver\ConnectionInterface;
use Dja\Db\Driver\ResultInterface;

use mysqli, mysqli_result, mysqli_stmt;

class Result implements ResultInterface
{
    /**
     * @var ConnectionInterface
     */
    protected $connection = null;

    /**
     * @var \mysqli_result|\mysqli_stmt
     */
    protected $resource = null;

    /**
     * @var bool
     */
    protected $isBuffered = null;

    /**
     * Cursor position
     * @var int
     */
    protected $position = 0;

    /**
     * Number of known rows
     * @var int
     */
    protected $numberOfRows = -1;

    /**
     * Is the current() operation already complete for this pointer position?
     * @var bool
     */
    protected $currentComplete = false;

    /**
     * @var bool
     */
    protected $nextComplete = false;

    /**
     * @var bool
     */
    protected $currentData = false;

    /**
     * @param \mysqli_result|\mysqli_stmt $resource
     * @param ConnectionInterface $connection
     * @throws \Exception
     */
    public function __construct($resource, ConnectionInterface $connection)
    {
        if ($resource instanceof \mysqli_stmt) {
            throw new \Exception('mysqli_stmt not implemented');
        }
        $this->resource = $resource;
        $this->connection = $connection;
    }


    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     */
    public function current()
    {
        if ($this->currentComplete) {
            return $this->currentData;
        }

        if ($this->resource instanceof \mysqli_stmt) {
            //$this->loadDataFromMysqliStatement();
            return $this->currentData;
        } else {
            $this->loadFromMysqliResult();
            return $this->currentData;
        }
    }

    /**
     * Load from mysqli result
     *
     * @return bool
     */
    protected function loadFromMysqliResult()
    {
        $this->currentData = null;

        if (($data = $this->resource->fetch_assoc()) === null) {
            return false;
        }

        $this->position++;
        $this->currentData = $data;
        $this->currentComplete = true;
        $this->nextComplete = true;
        $this->position++;
        return true;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        $this->currentComplete = false;

        if ($this->nextComplete == false) {
            $this->position++;
        }

        $this->nextComplete = false;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     */
    public function valid()
    {
        if ($this->currentComplete) {
            return true;
        }

        if ($this->resource instanceof \mysqli_stmt) {
            return $this->loadDataFromMysqliStatement();
        }

        return $this->loadFromMysqliResult();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @throws \RuntimeException
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        if ($this->position !== 0) {
            if ($this->isBuffered === false) {
                throw new \RuntimeException('Unbuffered results cannot be rewound for multiple iterations');
            }
        }
        $this->resource->data_seek(0); // works for both mysqli_result & mysqli_stmt
        $this->currentComplete = false;
        $this->position = 0;
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @throws \RuntimeException
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     */
    public function count()
    {
        if ($this->isBuffered === false) {
            throw new \RuntimeException('Row count is not available in unbuffered result sets.');
        }
        return $this->resource->num_rows;
    }

    /**
     * Force buffering
     *
     * @throws \RuntimeException
     * @return void
     */
    public function buffer()
    {
        if ($this->resource instanceof \mysqli_stmt && $this->isBuffered !== true) {
            if ($this->position > 0) {
                throw new \RuntimeException('Cannot buffer a result set that has started iteration.');
            }
            $this->resource->store_result();
            $this->isBuffered = true;
        }
    }

    /**
     * Check if is buffered
     *
     * @return bool|null
     */
    public function isBuffered()
    {
        return $this->isBuffered;
    }

    /**
     * Is query result?
     *
     * @return bool
     */
    public function isQueryResult()
    {
        return ($this->resource->field_count > 0);
    }

    /**
     * Get affected rows
     *
     * @return integer
     */
    public function getAffectedRows()
    {
        if ($this->resource instanceof \mysqli || $this->resource instanceof \mysqli_stmt) {
            return $this->resource->affected_rows;
        }

        return $this->resource->num_rows;
    }

    /**
     * Get generated value
     *
     * @return mixed|null
     */
    public function getGeneratedValue()
    {
        return $this->generatedValue;
    }

    /**
     * Get the resource
     *
     * @return mixed
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * Get field count
     *
     * @return integer
     */
    public function getFieldCount()
    {
        return $this->resource->field_count;
    }
}