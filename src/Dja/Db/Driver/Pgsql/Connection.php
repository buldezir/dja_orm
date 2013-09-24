<?php
/**
 * User: Alexander.Arutyunov
 * Date: 21.08.13
 * Time: 17:48
 */

namespace Dja\Db\Driver\Pgsql;

use Dja\Db\Driver\ConnectionInterface;
use Dja\Db\Driver\ResultInterface;

class Connection implements ConnectionInterface
{
    /**
     * @var
     */
    protected $resource = null;

    /**
     * Connection parameters
     *
     * @var array
     */
    protected $connectionParameters = array();

    /**
     * In transaction
     *
     * @var bool
     */
    protected $inTransaction = false;

    /**
     * @param array $connectionInfo
     * @throws \InvalidArgumentException
     */
    public function __construct($connectionInfo = null)
    {
        if (is_array($connectionInfo)) {
            $this->connectionParameters = $connectionInfo;
        } else {
            throw new \InvalidArgumentException('$connectionInfo must be an array of parameters');
        }
    }

    /**
     * Get current schema
     *
     * @return string
     */
    public function getCurrentSchema()
    {
        // TODO: Implement getCurrentSchema() method.
    }

    /**
     * Get resource
     *
     * @return mixed
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * Connect
     *
     * @throws \RuntimeException
     * @return ConnectionInterface
     */
    public function connect()
    {
        if (is_resource($this->resource)) {
            return $this;
        }

        $p = $this->connectionParameters;

        // given a list of key names, test for existence in $p
        $findParameterValue = function (array $names) use ($p) {
            foreach ($names as $name) {
                if (isset($p[$name])) {
                    return $p[$name];
                }
            }
            return null;
        };

        $connection             = array();
        $connection['host']     = $findParameterValue(array('hostname', 'host'));
        $connection['user']     = $findParameterValue(array('username', 'user'));
        $connection['password'] = $findParameterValue(array('password', 'passwd', 'pw'));
        $connection['dbname']   = $findParameterValue(array('database', 'dbname', 'db', 'schema'));
        $connection['port']     = (isset($p['port'])) ? (int) $p['port'] : null;
        $connection['socket']   = (isset($p['socket'])) ? $p['socket'] : null;

        $connection = array_filter($connection); // remove nulls
        $connection = http_build_query($connection, null, ' '); // @link http://php.net/pg_connect

        set_error_handler(function ($number, $string) {
            throw new \RuntimeException(
                __METHOD__ . ': Unable to connect to database', null, new \ErrorException($string, $number)
            );
        });
        $this->resource = pg_connect($connection);
        restore_error_handler();

        if ($this->resource === false) {
            throw new \RuntimeException(sprintf(
                '%s: Unable to connect to database',
                __METHOD__
            ));
        }

        return $this;
    }

    /**
     * Is connected
     *
     * @return bool
     */
    public function isConnected()
    {
        return (is_resource($this->resource));
    }

    /**
     * Disconnect
     *
     * @return ConnectionInterface
     */
    public function disconnect()
    {
        pg_close($this->resource);
        unset($this->resource);
        return $this;
    }

    /**
     * Begin transaction
     *
     * @return ConnectionInterface
     */
    public function beginTransaction()
    {
        $this->connect();
        $this->inTransaction = true;
        return $this;
    }

    /**
     * Commit
     *
     * @throws \RuntimeException
     * @return ConnectionInterface
     */
    public function commit()
    {
        if (!$this->isConnected()) {
            throw new \RuntimeException('Must be connected before you can commit.');
        }
        if (!$this->inTransaction) {
            throw new \RuntimeException('Must call beginTransaction() before you can commit.');
        }
        $this->inTransaction = false;
        return $this;
    }

    /**
     * Rollback
     *
     * @throws \RuntimeException
     * @return ConnectionInterface
     */
    public function rollback()
    {
        if (!$this->isConnected()) {
            throw new \RuntimeException('Must be connected before you can rollback.');
        }
        if (!$this->inTransaction) {
            throw new \RuntimeException('Must call commit() before you can rollback.');
        }
        return $this;
    }

    /**
     * Execute
     *
     * @param  string $sql
     * @throws \RuntimeException
     * @return ResultInterface
     */
    public function execute($sql)
    {
        $this->connect();
        $resultResource = pg_query($this->resource, $sql);
        if ($resultResource === false) {
            throw new \RuntimeException(pg_errormessage($this->resource));
        }
        return new Result($resultResource, $this);
    }

    /**
     * Prepare
     *
     * @param  string $sql
     * @throws \RuntimeException
     * @return ResultInterface
     */
    public function prepare($sql)
    {
        $this->connect();
        $stmt = $this->resource->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException($this->resource->error);
        }
        return $stmt;
    }

    /**
     * Get last generated id
     *
     * @param  null $name Ignored
     * @return integer
     */
    public function getLastGeneratedValue($name = null)
    {
        if ($name == null) {
            return null;
        }
        $result = pg_query($this->resource, 'SELECT CURRVAL(\'' . str_replace('\'', '\\\'', $name) . '\') as "currval"');
        return pg_fetch_result($result, 0, 'currval');
    }

}