<?php

namespace My\Util;

class AutoCollection implements \ArrayAccess
{
    /**
     * @var self
     */
    protected static $_inst;

    /**
     * @var array
     */
    protected $_data = array();

    /**
     * @var bool
     */
    protected $_isCollection = false;

    /**
     * @var string
     */
    protected $_dbTableName;

    /**
     * @return self
     */
    public static function getInstance()
    {
        if (!self::$_inst) {
            self::$_inst = new self(null, true);
        }
        return self::$_inst;
    }

    function __construct($dbTableName = null, $isCollection = false)
    {
        $this->_isCollection = $isCollection;
        if (!$isCollection && !$dbTableName) {
            throw new \InvalidArgumentException('Must define table name if it is not a collection!');
        }
        if (!$isCollection) {
            $this->_dbTableName = $dbTableName;
            $this->_data = $this->loadMetadataFromDb($dbTableName);
        }
    }


    public function loadMetadataFromDb($table)
    {

    }

    /**
     * @param string $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->_data[$offset]);
    }

    /**
     * @param string $offset
     * @throws \OutOfBoundsException
     * @return mixed
     */
    public function offsetGet($offset)
    {
        if (!isset($this->_data[$offset])) {
            if ($this->_isCollection) {
                $this->_data[$offset] = new self($offset);
            } else {
                throw new \OutOfBoundsException('No such offset');
            }
        }
        return $this->_data[$offset];
    }

    /**
     * @param string $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->_data[$offset] = $value;
    }

    /**
     * @param string $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->_data[$offset]);
    }


}