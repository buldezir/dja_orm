<?php

namespace Dja\Db\Model;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\GenericEvent as Event;

abstract class Model implements \ArrayAccess
{
    const EVENT_BEFORE_SAVE = 'event.beforeSave';
    const EVENT_AFTER_SAVE = 'event.afterSave';
    const EVENT_BEFORE_DELETE = 'event.beforeDelete';
    const EVENT_AFTER_DELETE = 'event.afterDelete';
//    const EVENT_BEFORE_INSERT = 'event.beforeInsert';
//    const EVENT_AFTER_INSERT = 'event.afterInsert';
//    const EVENT_BEFORE_UPDATE = 'event.beforeUpdate';
//    const EVENT_AFTER_UPDATE = 'event.afterUpdate';

    /**
     * @var string
     */
    protected static $dbtable;

    /**
     * @var array
     */
    protected static $fields = array();

    /**
     * @var EventDispatcher
     */
    protected static $eventd;

    /**
     * @var bool
     */
    protected $isNewRecord = true;

    /**
     * @var bool
     */
    protected $inited = false;

    /**
     * @var bool
     */
    protected $hydrated = false;

    /**
     * @var array
     */
    protected $data = array();

    /**
     * @var array
     */
    protected $cleanData = array();

    /**
     * @var array
     */
    protected $relationDataCache = array();

    /**
     * @var array
     */
    protected $validationErrors = array();

    /**
     * current model metadata
     * @return Metadata
     */
    public static function metadata()
    {
        return Metadata::getInstance(get_called_class());
    }

    /**
     * new queryset
     * an iterator represents db rows
     * @return Query
     */
    public static function objects()
    {
        return new Query(static::metadata());
    }

    /**
     * global events from Model::events()
     * model events from MyModelClass::events()
     * @return EventDispatcher
     */
    public static function events()
    {
        if (get_called_class() !== __CLASS__) {
            return static::metadata()->events();
        }
        if (!self::$eventd) {
            self::$eventd = new EventDispatcher();
        }
        return self::$eventd;
    }

    /**
     * todo: discuss about mechanics and usage of helpers
     * example:
     * User::getAllActive($param1, $param2) -> UserHelper->getAllActive($param1, $param2)
     *
     * @param string $name
     * @param array $arguments
     * @throws \Exception
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        $cc = get_called_class();
        $short_cc = substr($cc, strrpos($cc, '\\')+1);
        $helperClass = '\\App\\Helpers\\'.$short_cc.'Helper';
        try {
            class_exists($helperClass);
        } catch (\Exception $e) {
            throw new \Exception('helper "'.$helperClass.'" does not exist', 0, $e);
        }
        $inst = $helperClass::getInstance();
        if (!method_exists($inst, $name)) {
            throw new \Exception('helper method "'.$helperClass.'->'.$name.'()" does not exist');
        }
        return call_user_func_array(array($inst, $name), $arguments);
    }

    /**
     * receive array of raw data and combine it to local values and related objects
     * basicaly for Model::objects()->selectRelated() auto joins
     * @param array $rawData
     * @return $this
     */
    public function hydrate(array $rawData = array())
    {
        if ($this->hydrated) {
            return $this;
        }
        //dump($rawData);
        $relData = array();
        foreach ($rawData as $key => $value) {
            if (strpos($key, '__') !== false) {
                list($relKey, $relCol) = explode('__', $key);
                $relData[$relKey][$relCol] = $value;
            } else {
                // local:
                $this->$key = $value;
            }
        }
        foreach ($relData as $rel => $data) {
            $field = static::metadata()->getField($rel);
            if ($field->isRelation()) {
                if ($this->_get($field->db_column)) {
                    $relClass = $field->relationClass;
                    $this->relationDataCache[$rel] = new $relClass(false, $data);
                }
            }
        }

        $this->cleanData = $this->data;
        $this->hydrated = true;
        return $this;
    }

    /**
     * @param array $data
     * @param bool $isNewRecord
     * @return \Dja\Db\Model\Model
     */
    final public function __construct(array $data = array(), $isNewRecord = true)
    {
        $this->isNewRecord = $isNewRecord;
        static::metadata();
        if (!$isNewRecord) {
            $this->hydrate($data);
        } else {
            $this->setFromArray($data);
        }
        $this->init();
        $this->inited = true;
    }

    /**
     *  for overriding
     */
    protected function init()
    {
    }

    /**
     * dispatch local and global model events
     * @param string $eventName
     * @return Event
     */
    protected function eventDispatch($eventName)
    {
        $e = new Event($this);
        Model::events()->dispatch($eventName, $e);
        if ($e->isPropagationStopped()) {
            return $e;
        }
        static::metadata()->events()->dispatch($eventName, $e);
        return $e;
    }

    /**
     * set defaults from metadata
     * @param bool $force force default even if value exists
     * @return $this
     */
    protected function setDefaultValues($force = false)
    {
        foreach (static::metadata()->getLocalFields() as $field) {
            if ($field->default !== null) {
                if ($force === true || !isset($this->data[$field->db_column])) {
                    $this->data[$field->db_column] = $field->default;
                }
            }
        }
        return $this;
    }

    /**
     * @param $field
     * @param $errors
     * @return $this
     */
    protected function addValidationError($field, $errors)
    {
        if (!isset($this->validationErrors[$field])) {
            $this->validationErrors[$field] = array();
        }
        if (is_array($errors)) {
            foreach ($errors as $err) {
                $this->validationErrors[$field][] = $err;
            }
        } else {
            $this->validationErrors[$field][] = $errors;
        }
        return $this;
    }

    /**
     *
     */
    protected function validate()
    {
        foreach ($this->data as $key => $value) {
            $field = static::metadata()->getField($key);
            try {
                $field->validate($value);
            } catch (ValidationError $e) {
                $this->addValidationError($field->name, $e->getMessages());
            } catch (\Exception $e) {
                $this->addValidationError($field->name, $e->getMessage());
                $prev = $e;
                while ($prev = $prev->getPrevious()) {
                    $this->addValidationError($field->name, $prev->getMessage());
                }
            }
        }
    }

    /**
     * insert new, or update existed
     */
    public function save()
    {
        if (!$this->eventDispatch(self::EVENT_BEFORE_SAVE)->isPropagationStopped()) {
            if ($this->isNewRecord) {
                // set default data if saving new record
                $this->setDefaultValues();
                $newPK = static::objects()->insert($this);
                $this->data[static::metadata()->pk->db_column] = $newPK;
                $this->cleanData = $this->data;
                $this->isNewRecord = false;
            } else {
                $updData = array_diff($this->data, $this->cleanData);
                unset($updData[static::metadata()->pk->db_column]);
                if ($updData) {
                    static::objects()->filter('pk', $this->pk)->update($updData);
                }
            }
            $this->eventDispatch(self::EVENT_AFTER_SAVE);
        }
    }

    /**
     *  delete object from database
     */
    public function delete()
    {
        if (!$this->eventDispatch(self::EVENT_BEFORE_DELETE)->isPropagationStopped()) {
            static::objects()->filter('pk', $this->pk)->delete();
            $this->eventDispatch(self::EVENT_AFTER_DELETE);
        }
    }

    /**
     * reset changed data
     */
    public function reset()
    {
        $this->data = $this->cleanData;
    }

    /**
     * reload object data from database
     * @throws \Exception
     */
    public function refresh()
    {
        if ($this->isNewRecord()) {
            throw new \Exception('Cant refresh not stored object');
        }
        $values = static::objects()->filter('pk', $this->pk)->values();
        $this->setFromArray($values);
        $this->cleanData = $this->data;
    }

    /**
     * @param $name
     * @return mixed
     */
    protected function _get($name)
    {
        $metadata = self::metadata();
        $field = $metadata->getField($name);
        if ($metadata->isLocal($name)) {
            return $this->data[$name];
        } elseif ($metadata->isVirtual($name)) {
            if ($field->isRelation()) {
                if (!isset($this->relationDataCache[$name])) {
                    $this->relationDataCache[$name] = $field->getRelation($this);
                }
                return $this->relationDataCache[$name];
            } else {
                return $this->data[$field->db_column];
            }
        }
    }

    /**
     * @param $name
     * @param $value
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    protected function _set($name, $value)
    {
        $metadata = static::metadata();
        $field = $metadata->getField($name);
        if ($this->inited === true && $field->editable === false) {
            throw new \Exception("Field '{$name}' is read-only");
        }
        $value = $field->cleanValue($value);
        if ($value === null && !$field->is_null) {
            throw new \InvalidArgumentException("Field '{$name}' is not nullable");
        }
        if ($metadata->isLocal($name)) {
            $this->data[$name] = $value;
        } elseif ($metadata->isVirtual($name)) {
            if ($field->isRelation()) {
                $this->relationDataCache[$name] = $value;
                $this->data[$field->db_column] = $value->__get($field->to_field);
            } else {
                $this->data[$field->db_column] = $value;
            }
        }
    }

    /**
     * @param string $name
     * @return mixed|int
     * @throws \OutOfRangeException
     */
    public function __get($name)
    {
        $getter = 'get' . $this->transformVarName($name);
        if (method_exists($this, $getter)) {
            return $this->$getter();
        } else {
            return $this->_get($name);
            //throw new \OutOfRangeException("value with key '{$name}' does not exist!");
        }
    }

    /**
     * @param string $name
     * @param mixed $value
     * @throws \Exception
     * @return void
     */
    public function __set($name, $value)
    {
        $setter = 'set' . $this->transformVarName($name);
        if (method_exists($this, $setter)) {
            $this->$setter($value);
        } else {
            $this->_set($name, $value);
        }
    }

    /**
     * @param $name
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset(static::metadata()->$name);
    }

    /**
     * @param $name
     * @return void
     */
    public function __unset($name)
    {
        //unset($this->data[$name]);
        $this->__set($name, null);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        foreach ($this->relationDataCache as $c) {
            if ($c instanceof Model) {
                dump($c->toArray());
            }
        }
        return $this->data;
    }

    public function export()
    {
        $result = array();
        foreach (static::metadata()->getLocalFields() as $field) {
            if ($field->isRelation()) {
                $value = $field->viewValue($this->__get($field->name));
            } else {
                $value = $field->viewValue($this->__get($field->db_column));
            }
            $name = $field->verbose_name ? $field->verbose_name : implode(' ', array_map('ucfirst', explode('_', $field->name)));
            $result[$field->db_column] = array(
                'name' => $name,
                'value' => $value,
                'choices' => $field->choices,

            );
        }
        return $result;
    }

    /**
     * @param array $data
     * @return $this
     */
    public function setFromArray(array $data)
    {
        $metadata = static::metadata();
        foreach ($data as $k => $v) {
            if (isset($metadata->$k)) {
                $this->$k = $v;
            }
        }
        return $this;
    }

    /**
     * @return bool
     */
    public function isNewRecord()
    {
        return $this->isNewRecord;
    }

    /**
     * @param string $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $this->__isset($offset);
    }

    /**
     * @param string $offset
     * @return mixed
     * @throws \OutOfRangeException
     */
    public function offsetGet($offset)
    {
        return $this->__get($offset);
    }

    /**
     * @param string $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->__set($offset, $value);
    }

    /**
     * @param string $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        $this->__unset($offset);
    }

    /**
     * under_score to CamelCase
     * @param string $name
     * @return string
     */
    protected function transformVarName($name)
    {
        return implode('', array_map('ucfirst', explode('_', $name)));
    }
}