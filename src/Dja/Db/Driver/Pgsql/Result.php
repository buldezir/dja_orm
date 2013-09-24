<?php
/**
 * User: Alexander.Arutyunov
 * Date: 22.08.13
 * Time: 11:02
 */

namespace Dja\Db\Driver\Pgsql;

use Dja\Db\Driver\ConnectionInterface;
use Dja\Db\Driver\ResultInterface;


class Result implements ResultInterface
{
    /**
     * @var ConnectionInterface
     */
    protected $connection = null;

    /**
     * @var resource
     */
    protected $resource = null;

    /**
     * Cursor position
     * @var int
     */
    protected $position = 0;

    /**
     * Number of known rows
     * @var int
     */
    protected $count = 0;

    /**
     * @param $resource
     * @param ConnectionInterface $connection
     * @throws \InvalidArgumentException
     */
    public function __construct($resource, ConnectionInterface $connection)
    {
        if (!is_resource($resource) || get_resource_type($resource) != 'pgsql result') {
            throw new \InvalidArgumentException('Resource not of the correct type.');
        }
        $this->resource = $resource;
        $this->connection = $connection;
        $this->count = pg_num_rows($this->resource);
    }


    /**
     * Current
     *
     * @return array|bool|mixed
     */
    public function current()
    {
        if ($this->count === 0) {
            return false;
        }
        return pg_fetch_assoc($this->resource, $this->position);
    }

    /**
     * Next
     *
     * @return void
     */
    public function next()
    {
        $this->position++;
    }

    /**
     * Key
     *
     * @return int|mixed
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * Valid
     *
     * @return bool
     */
    public function valid()
    {
        return ($this->position < $this->count);
    }

    /**
     * Rewind
     *
     * @return void
     */
    public function rewind()
    {
        $this->position = 0;
    }

    /**
     * Buffer
     *
     * @return null
     */
    public function buffer()
    {
        return null;
    }

    /**
     * Is buffered
     *
     * @return false
     */
    public function isBuffered()
    {
        return false;
    }

    /**
     * Is query result
     *
     * @return bool
     */
    public function isQueryResult()
    {
        return (pg_num_fields($this->resource) > 0);
    }

    /**
     * Get affected rows
     *
     * @return int
     */
    public function getAffectedRows()
    {
        return pg_affected_rows($this->resource);
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
     * Get resource
     */
    public function getResource()
    {
        // TODO: Implement getResource() method.
    }

    /**
     * Count
     *
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     */
    public function count()
    {
        return $this->count;
    }

    /**
     * Get field count
     *
     * @return int
     */
    public function getFieldCount()
    {
        return pg_num_fields($this->resource);
    }
}