<?php

namespace My\Model;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\GenericEvent as Event;

abstract class Model implements \ArrayAccess
{
    const EVENT_BEFORE_SAVE = 'event.beforeSave';
    const EVENT_AFTER_SAVE = 'event.afterSave';
    const EVENT_BEFORE_DELETE = 'event.beforeDelete';
    const EVENT_AFTER_DELETE = 'event.afterDelete';
//    const EVENT_BEFORE_INSERT = 'event.beforeInsert';
//    const EVENT_AFTER_INSERT = 'event.afterSave';
//    const EVENT_BEFORE_UPDATE = 'event.beforeUpdate';
//    const EVENT_AFTER_UPDATE = 'event.afterUpdate';

    /**
     * @var string
     */
    protected static $primary;

    /**
     * @var string
     */
    protected static $dbtable;

    /**
     * @var array
     */
    protected static $fields;

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
     * @return Metadata
     */
    public static function metadata()
    {
        return Metadata::getInstance(get_called_class());
    }

    /**
     * @return Query
     */
    public static function objects()
    {
        return new Query(static::metadata());
    }

    /**
     * @return EventDispatcher
     */
    public static function events()
    {
        if (get_called_class() !== 'My\Model\Model') {
            return static::metadata()->events();
        }
        if (!self::$eventd) {
            self::$eventd = new EventDispatcher();
        }
        return self::$eventd;
    }

    /**
     * @return $this
     */
    public function hydrate()
    {
        if ($this->hydrated) {
            return $this;
        }
//        dump(__METHOD__);
        $this->hydrated = true;
        return $this;
    }

    /**
     * @param bool $isNewRecord
     */
    final public function __construct($isNewRecord = true)
    {
        $this->isNewRecord = $isNewRecord;
        $this->cleanData = $this->data;
        $this->init();
        $this->inited = true;
        static::metadata();
        if (!$isNewRecord) {
            $this->hydrate();
        }
    }

    /**
     *
     */
    protected function init()
    {
    }

    /**
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
     *
     */
    public function save()
    {
        if (!$this->eventDispatch(self::EVENT_BEFORE_SAVE)->isPropagationStopped()) {
            if ($this->isNewRecord) {
                // set default data if saving new record
                foreach (static::metadata()->getLocalFields() as $field) {
                    if ($field->default !== null) {
                        if (!isset($this->data[$field->db_column])) {
                            $this->data[$field->db_column] = $field->default;
                        }
                    }
                }
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
     *
     */
    public function delete()
    {
        if (!$this->eventDispatch(self::EVENT_BEFORE_DELETE)->isPropagationStopped()) {
            static::objects()->filter('pk', $this->pk)->delete();
            $this->eventDispatch(self::EVENT_AFTER_DELETE);
        }
    }

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
            return $this->data[$field->db_column];
        }
    }

    /**
     * @param $name
     * @param $value
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
        if ($metadata->isLocal($name)) {
            $this->data[$name] = $value;
        } elseif ($metadata->isVirtual($name)) {
            $this->data[$field->db_column] = $value;
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
        return isset($this->data[$name]);
    }

    /**
     * @param $name
     * @return void
     */
    public function __unset($name)
    {
        unset($this->data[$name]);
    }

    /**
     *
     * @param string $name
     * @param array $arguments
     */
//    public static function __callStatic($name, $arguments)
//    {
//        $class = get_called_class();
//        // TODO: вот тут запилить хэлперы для "табличных" операций
//    }


    /**
     * @return array
     */
    public function toArray()
    {
        return $this->data;
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