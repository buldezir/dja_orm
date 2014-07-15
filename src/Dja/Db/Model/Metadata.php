<?php

namespace Dja\Db\Model;

use Dja\Util\Inflector;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\GenericEvent as Event;

class Metadata
{
    const EVENT_AFTER_CONFIG = 'event.afterConfigInit';
    const EVENT_AFTER_ADD = 'event.afterFieldAdd';
    const EVENT_AFTER_INIT = 'event.afterFieldsInit';

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected static $defaultDbConn;

    /**
     * @var array
     */
    protected static $instances = [];

    /**
     * @var string
     */
    protected $modelClassName;

    /**
     * @var string
     */
    protected $dbTableName;

    /**
     * @var EventDispatcher
     */
    protected $eventd;

    /**
     * array of inited field objects
     * @var Field\Base[]
     */
    protected $_localFields = [];

    /**
     * @var Field\Base[]
     */
    protected $_many2manyFields = [];

    /**
     * @var Field\Base[]
     */
    protected $_virtualFields = [];

    /**
     * @var Field\Base[]
     */
    protected $_allFields = []; // just cache

    /**
     * @var array
     */
    protected $gettersAndSetters = ['get' => [], 'set' => []];

    /**
     * temporary storage for fields config while initing
     * @var array
     */
    protected $fieldsTmp = [];

    /**
     * @param $modelClass
     * @return self
     */
    public static function getInstance($modelClass)
    {
        if (!isset(self::$instances[$modelClass])) {
            self::$instances[$modelClass] = new static($modelClass);
            // workaround to avoid endless cycle when this field init remote field, and remote field use this model metadata before __construct ends
            self::$instances[$modelClass]->initFields();
            $modelClass::initOnce();
        }
        return self::$instances[$modelClass];
    }

    /**
     * @param $modelClass
     * @throws \LogicException
     * @throws \InvalidArgumentException
     */
    public function __construct($modelClass)
    {
        $refl = new \ReflectionClass($modelClass);
        if ($refl->isAbstract()) {
            throw new \InvalidArgumentException("Cannot create metadata manager for abstract class '{$modelClass}'");
        }
        if (!$refl->isSubclassOf('\\Dja\\Db\\Model\\Model')) {
            throw new \LogicException("Cannot create metadata manager for class '{$modelClass}' that is not subclass of Model");
        }
        $this->modelClassName = $modelClass;
        $this->eventd = new EventDispatcher();
        $this->collectConfig($refl);
    }

    /**
     * @param $fName
     * @return null
     */
    public function getterName($fName)
    {
        return isset($this->gettersAndSetters['get'][$fName]) ? $this->gettersAndSetters['get'][$fName] : null;
    }

    /**
     * @param $fName
     * @return null
     */
    public function setterName($fName)
    {
        return isset($this->gettersAndSetters['set'][$fName]) ? $this->gettersAndSetters['set'][$fName] : null;
    }

    /**
     * @param \ReflectionClass $refl
     */
    protected function collectGettersSetters(\ReflectionClass $refl)
    {
        foreach (array_keys($this->fieldsTmp) as $fName) {
            $cc = Inflector::classify($fName);
            $getter = 'get' . $cc;
            if ($refl->hasMethod($getter)) {
                $this->gettersAndSetters['get'][$fName] = $getter;
            }
            $setter = 'set' . $cc;
            if ($refl->hasMethod($setter)) {
                $this->gettersAndSetters['set'][$fName] = $setter;
            }
        }
    }

    /**
     * @param \ReflectionClass $refl
     * @throws \UnderflowException
     */
    protected function collectConfig(\ReflectionClass $refl)
    {
        if ($refl->hasMethod('config')) {
            $conf = $refl->getMethod('config')->invoke(null);
            if (!isset($conf['fields'])) {
                throw new \UnderflowException('U must declare "fields" in config');
            }
            $this->fieldsTmp = $conf['fields'];
            $this->dbTableName = isset($conf['dbtable']) ? $conf['dbtable'] : null;
            if (isset($conf['events'])) {
                $checkEvents = [
                    static::EVENT_AFTER_CONFIG,
                    static::EVENT_AFTER_INIT,
                    static::EVENT_AFTER_ADD,
                ];
                foreach ($checkEvents as $ev) {
                    if (isset($conf['events'][$ev])) {
                        $this->events()->addListener($ev, $conf['events'][$ev]);
                    }
                }
            }
        } else {
            $staticProps = $refl->getStaticProperties();
            if (empty($staticProps['fields'])) {
                throw new \UnderflowException('U must declare static property "fields"');
            }
            $this->fieldsTmp = $this->collectFieldConfig($refl);
            $this->dbTableName = !empty($staticProps['dbtable']) ? $staticProps['dbtable'] : null;
            $conf = [
                'fields' => $this->fieldsTmp,
                'dbtable' => $this->dbTableName,
            ];
        }
        $this->events()->dispatch(self::EVENT_AFTER_CONFIG, new Event($this, $conf));
    }

    /**
     * for model inheritance
     * @param \ReflectionClass $refl
     * @return array
     */
    protected function collectFieldConfig(\ReflectionClass $refl)
    {
        $fields = [];
        $tree = [];
        $parent = $refl;
        while ($parent && $parent->getShortName() !== 'Model') {
            $tree[] = $parent;
            $parent = $parent->getParentClass();
        }
        /** @var \ReflectionClass[] $tree */
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
     * @return $this
     */
    public function addField($name, array $options)
    {
        $this->_addField($name, $options);
        return $this;
    }

    /**
     * @param $name
     * @return $this
     */
    public function removeField($name)
    {
        $f = $this->getField($name);
        unset($this->_allFields[$name], $this->_localFields[$name], $this->_many2manyFields[$name], $this->_virtualFields[$name]);
        unset($this->_allFields[$f->db_column], $this->_localFields[$f->db_column], $this->_many2manyFields[$f->db_column], $this->_virtualFields[$f->db_column]);
        return $this;
    }

    /**
     * @param string $name
     * @param array|Field\Base $options
     * @throws \Exception
     */
    protected function _addField($name, $options)
    {
        $baseClass = __NAMESPACE__ . '\\Field\\Base';
        if (is_array($options)) {
            $fieldClass = array_shift($options);
            if ($fieldClass{0} !== '\\') {
                $fieldClass = __NAMESPACE__ . '\\Field\\' . $fieldClass;
            }
            $options['name'] = $name;
            $options['ownerClass'] = $this->modelClassName;
            /** @var Field\Base $fieldObj */
            $fieldObj = new $fieldClass($options);
            if (!$fieldObj instanceof $baseClass) {
                throw new \Exception("Field '$name' class must be subclass of '$baseClass'");
            }
        } elseif ($options instanceof $baseClass) {
            $fieldObj = $options;
            $fieldObj->setOption('name', $name);
            $fieldObj->setOption('ownerClass', $this->modelClassName);
        } else {
            throw new \Exception("Field '$name' \$options must be array or object subclass of '$baseClass'");
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
        foreach ($this->fieldsTmp as $name => $options) {
            $this->_addField($name, $options);
        }
        $this->events()->dispatch(self::EVENT_AFTER_INIT, new Event($this, $this->fieldsTmp));
        unset($this->fieldsTmp);
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
     *
     * @return array
     */
    public function getDefaultValues()
    {
        $result = [];
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
        $result = [];
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
     * @param string $key
     * @return Field\Base|Field\Relation
     * @throws \LogicException
     */
    public function findField($key)
    {
        if (strpos($key, '__') === false) {
            return $this->getField($key);
        } else {
            $lookupArr = explode('__', $key);
            $f = array_shift($lookupArr);
            $field = $this->getField($f);
            if ($field->isRelation()) {
                /** @var \Dja\Db\Model\Field\Relation $field */
                return $field->getRelationMetadata()->findField(implode('__', $lookupArr));
            } else {
                throw new \LogicException("Cant find deeper, because {$f} is not relation");
            }
        }
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
     * return manual setted or generate table name
     * User -> users, UserRole -> user_roles
     * @return null|string
     */
    public function getDbTableName()
    {
        if ($this->dbTableName === null) {
            $this->dbTableName = Inflector::namespacedTableize($this->modelClassName);
        }
        return $this->dbTableName;
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