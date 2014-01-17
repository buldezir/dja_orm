<?php

namespace Dja\Db\Model;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\GenericEvent as Event;

class Metadata
{
    const EVENT_AFTER_INIT = 'event.afterFieldsInit';
    const EVENT_AFTER_ADD = 'event.afterFieldAdd';

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected static $defaultDbConn;

    /**
     * @var array
     */
    protected static $instances = array();

    /**
     * @var string
     */
    protected $modelClassName;

    /**
     * @var string
     */
    protected $dbTableName;

    protected $primaryKey;

    /**
     * @var EventDispatcher
     */
    protected $eventd;

    /**
     * array or inited field objects
     * @var array
     */
    protected $_localFields = array();
    protected $_many2manyFields = array();
    protected $_virtualFields = array();

    protected $_allFields = array(); // just cache

    /**
     * @param $modelClass
     * @return self
     */
    public static function getInstance($modelClass)
    {
        if (!isset(self::$instances[$modelClass])) {
            self::$instances[$modelClass] = new self($modelClass);
            // workaround to avoid endless cycle when this field init remote field, and remote field use this model metadata before __construct ends
            self::$instances[$modelClass]->initFields();
            $modelClass::initOnce();
        }
        return self::$instances[$modelClass];
    }

    /**
     * return manual setted or generate table name
     * User -> users, UserRole -> user_roles
     * @return null|string
     */
    public function getDbTableName()
    {
        if ($this->dbTableName === null) {
            $parts = preg_split('#[\\-]#', $this->modelClassName);
            $lastPart = array_pop($parts);
            $name = $this->camelCaseToUnderscore($lastPart) . 's';
            $this->dbTableName = $name;
        }
        return $this->dbTableName;
    }

    /**
     * @param string $value
     * @return string
     */
    public function camelCaseToUnderscore($value)
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $value));
    }

    /**
     * @param $modelClass
     */
    public function __construct($modelClass)
    {
        $refl = new \ReflectionClass($modelClass);
        $staticProps = $refl->getStaticProperties();
        $dbTableName = $staticProps['dbtable'];
        $this->fields = $this->collectFieldConfig($refl);
        if ($dbTableName !== null) {
            $this->dbTableName = $dbTableName;
        }
        $this->modelClassName = $modelClass;
        $this->eventd = new EventDispatcher();
    }

    /**
     * @param array $data
     * @return array
     */
    public function filterData(array $data)
    {
        foreach ($data as $key => $value) {
            if (isset($this->$key)) {
                $data[$key] = $this->getField($key)->fromDbValue($value);
            }
        }
        return $data;
    }

    /**
     * for model inheritance
     * @param \ReflectionClass $refl
     * @return array
     */
    protected function collectFieldConfig(\ReflectionClass $refl)
    {
        $fields = array();
        $tree = array();
        $parent = $refl;
        while ($parent && $parent->getShortName() !== 'Model') {
            $tree[] = $parent;
            $parent = $parent->getParentClass();
        }
        $tree = array_reverse($tree);
        foreach ($tree as $refl) {
            $staticProps = $refl->getStaticProperties();
            $fields = array_merge($fields, $staticProps['fields']);
        }
        return $fields;
    }

    /**
     * @param string $name
     * @param array $options
     * @param bool $throw
     * @return $this
     */
    public function addField($name, array $options, $throw = false)
    {
        if ($throw) {
            $this->_addField($name, $options);
        } else {
            try {
                $this->_addField($name, $options);
            } catch (\Exception $e) {
                echo '<pre>' . $e . '</pre>';
                /* @todo: error handler */
            }
        }
        return $this;
    }

    /**
     * @param string $name
     * @param array $options
     * @throws \Exception
     */
    protected function _addField($name, array $options)
    {
        $fieldClass = array_shift($options);
        if ($fieldClass{0} !== '\\') {
            $fieldClass = __NAMESPACE__ . '\\Field\\' . $fieldClass;
        }
        $options['name'] = $name;
        $options['ownerClass'] = $this->modelClassName;
        /** @var Field\Base $fieldObj */
        $fieldObj = new $fieldClass($options);
        $baseClass = __NAMESPACE__ . '\\Field\\Base';
        if (!$fieldObj instanceof $baseClass) {
            throw new \Exception("Field '$name' class must be subclass of '$baseClass'");
        }
        $fieldObj->setMetadata($this);
        $fieldObj->init();
        if ($fieldObj instanceof Field\ManyRelation) {
            if (array_key_exists($name, $this->_allFields)) {
                throw new \Exception("Cant be fields with same name or db_column! ($name)");
            } else {
                $this->_many2manyFields[$name] = $fieldObj;
                $this->_allFields[$name] = $fieldObj;
                $this->_virtualFields[$name] = $fieldObj;
            }
        } else {
            if ($fieldObj->isRelation()) {
                if (array_key_exists($fieldObj->db_column, $this->_allFields) || array_key_exists($name, $this->_allFields)) {
                    throw new \Exception("Cant be fields with same name or db_column! ($name)");
                } else {
                    $this->_localFields[$fieldObj->db_column] = $fieldObj;
                    $this->_virtualFields[$name] = $fieldObj;
                    $this->_allFields[$fieldObj->db_column] = $fieldObj;
                    $this->_allFields[$name] = $fieldObj;
                }
            } else {
                if (array_key_exists($name, $this->_allFields)) {
                    throw new \Exception("Cant be fields with same name or db_column! ($name)");
                } else {
                    $this->_localFields[$name] = $fieldObj;
                    $this->_allFields[$name] = $fieldObj;
                }
            }
            if ($fieldObj->primary_key) {
                if (array_key_exists('pk', $this->_virtualFields)) {
                    throw new \Exception("More than 1 primary key is not allowed! ($name)");
                } else {
                    if (array_key_exists('pk', $this->_allFields)) {
                        throw new \Exception("Cant be fields with same name or db_column! ($name)");
                    } else {
                        $this->_virtualFields['pk'] = $fieldObj;
                        $this->_allFields['pk'] = $fieldObj;
                    }
                }
            }
        }
        $this->events()->dispatch(self::EVENT_AFTER_ADD, new Event($fieldObj, $options));
    }

    /**
     * delayed init
     */
    public function initFields()
    {
        foreach ($this->fields as $name => $options) {
            $this->_addField($name, $options);
        }
        $this->events()->dispatch(self::EVENT_AFTER_INIT, new Event($this, $this->fields));
        unset($this->fields);
    }

    /**
     *
     * @return array
     */
    public function getDefaultValues()
    {
        $result = array();
        foreach ($this->_localFields as $name => $fieldObj) {
            $result[$fieldObj->db_column] = $fieldObj->default;
        }
        return $result;
    }

    /**
     *
     * @return Field\Base[]
     */
    public function getRelationFields()
    {
        $result = array();
        foreach ($this->_localFields as $name => $fieldObj) {
            if ($fieldObj->isRelation()) {
                $result[$fieldObj->db_column] = $fieldObj;
            }
        }
        return $result;
    }

    /**
     * @param $key
     * @return Field\Base
     */
    public function __get($key)
    {
        return $this->getField($key);
    }

    /**
     * @param $key
     * @return bool
     */
    public function __isset($key)
    {
        return array_key_exists($key, $this->_allFields);
    }

    /**
     * @return int
     */
    public function countLocalFields()
    {
        return count($this->_localFields);
    }

    /**
     *
     * @param string $key
     * @throws \Exception
     * @return Field\Base
     */
    public function getField($key)
    {
        if (isset($this->_allFields[$key])) {
            return $this->_allFields[$key];
        } else {
            throw new \OutOfRangeException("field with name or db_column '{$key}' does not exist!");
            //throw new \Exception('No field with name or db_column = "' . $key . '"');
        }
    }

    /**
     * @return Field\Base[]
     */
    public function getFields()
    {
        return $this->_allFields;
    }

    /**
     * @return Field\Base[]
     */
    public function getLocalFields()
    {
        return $this->_localFields;
    }

    /**
     * @return Field\Base[]
     */
    public function getVirtualFields()
    {
        return $this->_virtualFields;
    }

    /**
     * @return Field\Base[]
     */
    public function getMany2ManyFields()
    {
        return $this->_many2manyFields;
    }

    /**
     * @param Field\Base $field
     * @return bool
     */
    public function hasFieldObj(Field\Base $field)
    {
        foreach ($this->_allFields as $f) {
            if ($f === $field) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $key
     * @return bool
     */
    public function isLocal($key)
    {
        return isset($this->_localFields[$key]);
    }

    /**
     * @param $key
     * @return bool
     */
    public function isVirtual($key)
    {
        return isset($this->_virtualFields[$key]);
    }

    /**
     * @param $key
     * @return bool
     */
    public function isM2M($key)
    {
        return isset($this->_many2manyFields[$key]);
    }

    /**
     * @param $name
     * @return bool
     */
    public function canBeHydrated($name)
    {
        if (isset($this->_allFields[$name])) {
            if ($this->_allFields[$name]->isRelation() && $this->_allFields[$name]->canAutoJoin()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array
     */
    public function getDbColNames()
    {
        $result = array();
        foreach ($this->_localFields as $fieldObj) {
            $result[] = $fieldObj->db_column;
        }
        return $result;
    }

    /**
     * @return EventDispatcher
     */
    public function events()
    {
        return $this->eventd;
    }

    /**
     * @return string
     */
    public function getPrimaryKey()
    {
        return $this->pk->db_column;
    }

    /**
     * @return string
     */
    public function getModelClass()
    {
        return $this->modelClassName;
    }

    /**
     * @return \Doctrine\DBAL\Connection
     */
    public function getDbConnection()
    {
        return self::getDefaultDbConnection();
    }

    /**
     * @return \Doctrine\DBAL\Connection
     */
    public static function getDefaultDbConnection()
    {
        if (null === self::$defaultDbConn) {
            self::$defaultDbConn = \Doctrine\DBAL\DriverManager::getConnection(array(
                'driver' => 'pdo_pgsql',
                'dbname' => 'sasha',
                'user' => 'sasha',
                'password' => '',
                'host' => 'localhost',
                //'port' => 6432,
            ));
        }
        return self::$defaultDbConn;
    }

    /**
     * @param \Doctrine\DBAL\Connection $conn
     */
    public static function setDefaultDbConnection(\Doctrine\DBAL\Connection $conn)
    {
        self::$defaultDbConn = $conn;
    }
}