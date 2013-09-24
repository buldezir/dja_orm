<?php
/**
 * User: Alexander.Arutyunov
 * Date: 21.08.13
 * Time: 17:48
 */

namespace Dja\Db\Driver\Mysqli;

use Dja\Db\Driver\ConnectionInterface;
use Dja\Db\Driver\ResultInterface;

use mysqli, mysqli_result, mysqli_stmt;

class Connection implements ConnectionInterface
{
    /**
     * @var \mysqli
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
        if ($this->resource !== null) {
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
            return;
        };

        $hostname = $findParameterValue(array('hostname', 'host'));
        $username = $findParameterValue(array('username', 'user'));
        $password = $findParameterValue(array('password', 'passwd', 'pw'));
        $database = $findParameterValue(array('database', 'dbname', 'db', 'schema'));
        $port     = (isset($p['port'])) ? (int) $p['port'] : null;
        $socket   = (isset($p['socket'])) ? $p['socket'] : null;

        $this->resource = new \mysqli();
        $this->resource->init();

        if (!empty($p['driver_options'])) {
            foreach ($p['driver_options'] as $option => $value) {
                if (is_string($option)) {
                    $option = strtoupper($option);
                    if (!defined($option)) {
                        continue;
                    }
                    $option = constant($option);
                }
                $this->resource->options($option, $value);
            }
        }

        $this->resource->real_connect($hostname, $username, $password, $database, $port, $socket);

        if ($this->resource->connect_error) {
            throw new \RuntimeException(
                'Connection error',
                null,
                new \ErrorException($this->resource->connect_error, $this->resource->connect_errno)
            );
        }

        if (!empty($p['charset'])) {
            $this->resource->set_charset($p['charset']);
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
        return ($this->resource instanceof \mysqli);
    }

    /**
     * Disconnect
     *
     * @return ConnectionInterface
     */
    public function disconnect()
    {
        if ($this->resource instanceof \mysqli) {
            $this->resource->close();
        }
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
        $this->resource->autocommit(false);
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
        if (!$this->resource) {
            throw new \RuntimeException('Must be connected before you can commit.');
        }
        if (!$this->inTransaction) {
            throw new \RuntimeException('Must call beginTransaction() before you can commit.');
        }
        $this->resource->commit();
        $this->inTransaction = false;
        $this->resource->autocommit(true);
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
        if (!$this->resource) {
            throw new \RuntimeException('Must be connected before you can rollback.');
        }
        if (!$this->inTransaction) {
            throw new \RuntimeException('Must call commit() before you can rollback.');
        }
        $this->resource->rollback();
        $this->resource->autocommit(true);
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
        $resultResource = $this->resource->query($sql);
        if ($resultResource === false) {
            throw new \RuntimeException($this->resource->error);
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
        return $this->resource->insert_id;
    }

}