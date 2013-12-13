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
    protected static $fields = [];

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
    protected $data = [];

    /**
     * @var array
     */
    protected $cleanData = [];

    /**
     * @var array
     */
    protected $relationDataCache = [];

    /**
     * @var array
     */
    protected $validationErrors = [];

    /** @var Metadata */
    protected $metadata;

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
     * @param bool $biiig
     * @return Query
     */
    public static function objects($biiig = false)
    {
        if (false === $biiig) {
            return new Query(static::metadata());
        } else {
            return new RowsetQuery(static::metadata());
        }
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
//    public static function __callStatic($name, $arguments)
//    {
//        $cc = get_called_class();
//        $short_cc = substr($cc, strrpos($cc, '\\') + 1);
//        $helperClass = '\\App\\Helpers\\' . $short_cc . 'Helper';
//        try {
//            class_exists($helperClass);
//        } catch (\Exception $e) {
//            throw new \Exception('helper "' . $helperClass . '" does not exist', 0, $e);
//        }
//        $inst = $helperClass::getInstance();
//        if (!method_exists($inst, $name)) {
//            throw new \Exception('helper method "' . $helperClass . '->' . $name . '()" does not exist');
//        }
//        return call_user_func_array(array($inst, $name), $arguments);
//    }

    /**
     * @param array $data
     * @param bool $isNewRecord
     * @param bool $fastRawSet tweak for fast data setup
     * @return \Dja\Db\Model\Model
     */
    public function __construct(array $data = array(), $isNewRecord = true, $fastRawSet = false)
    {
        $this->isNewRecord = $isNewRecord;
        $this->metadata = static::metadata();
        if ($isNewRecord) {
            $this->setFromArray($data);
        } else {
            $this->hydrate($data, $fastRawSet);
        }
        $this->init();
        $this->inited = true;
    }

    /**
     * receive array of raw data and combine it to local values and related objects
     * basicaly for Model::objects()->selectRelated() auto joins
     * @param array $rawData
     * @param bool $fastRawSet tweak for fast data setup w/o filtering and validating
     * @return $this
     */
    public function hydrate(array $rawData = array(), $fastRawSet = false)
    {
        if ($this->hydrated) {
            return $this;
        }
        //dump($rawData);
        $relData = array();
        foreach ($rawData as $key => $value) {
            if (strpos($key, '__') !== false) {
                //list($relKey, $relCol) = explode('__', $key);
                $tmp = explode('__', $key);
                $relKey = array_shift($tmp);
                $relData[$relKey][implode('__', $tmp)] = $value;
            } else {
                // todo: think about workaround, because method_exists give biiig cpu overhead
                // local:
//                $setter = 'set' . $this->transformVarName($key);
//                if (method_exists($this, $setter)) {
//                    $this->$setter($value);
//                } else {
//                    $this->_set($key, $value, true);
//                }
                if ($fastRawSet) {
                    $this->data[$key] = $value;
                } else {
                    $this->_set($key, $value, true);
                }
            }
        }
        foreach ($relData as $rel => $data) {
            $field = $this->metadata->getField($rel);
            if ($field->isRelation()) {
                if ($this->_get($field->db_column)) {
                    $relClass = $field->relationClass;
                    $this->relationDataCache[$rel] = new $relClass($data, false, $fastRawSet);
                }
            }
        }
        $this->cleanData = $this->data;
        $this->hydrated = true;
        return $this;
    }

    /**
     * dispatch global and local model events
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
        $this->metadata->events()->dispatch($eventName, $e);
        return $e;
    }

    /**
     * set defaults from metadata
     * @param bool $force force default even if value exists
     * @return $this
     */
    protected function setDefaultValues($force = false)
    {
        foreach ($this->metadata->getLocalFields() as $field) {
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
     * @return array
     */
    public function validate()
    {
        foreach ($this->data as $key => $value) {
            $field = $this->metadata->getField($key);
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
        return $this->validationErrors;
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
                $this->data[$this->metadata->pk->db_column] = $newPK;
            } else {
                $updData = $this->getChangedValues();
                //unset($updData[$this->metadata->pk->db_column]);
                if (count($updData) > 0) {
                    static::objects()->filter(['pk' => $this->pk])->update($updData);
                }
            }
            $this->eventDispatch(self::EVENT_AFTER_SAVE);
            $this->isNewRecord = false; // with this we can tell in EVENT_AFTER_SAVE was it update or insert action
            $this->cleanData = $this->data;
        }
    }

    /**
     *  delete object from database
     */
    public function delete()
    {
        if (!$this->eventDispatch(self::EVENT_BEFORE_DELETE)->isPropagationStopped()) {
            static::objects()->filter(['pk' => $this->pk])->delete();
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
     * @return array
     */
    public function getChangedValues()
    {
        return array_udiff_assoc($this->data, $this->cleanData, function($a, $b){ if ($a === $b) { return 0; } else return 1; });
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
        $field = $this->metadata->getField($name);
        if ($this->metadata->isLocal($name)) {
            return $this->data[$name];
        } elseif ($this->metadata->isVirtual($name)) {
            if ($field->isRelation()) {
                if (!isset($this->relationDataCache[$name])) {
                    /** @var Field\ForeignKey $field */
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
     * @param bool $raw
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    protected function _set($name, $value, $raw = false)
    {
        $field = $this->metadata->getField($name);
        if ($this->inited === true && $field->editable === false) {
            throw new \Exception("Field '{$name}' is read-only");
        }
        if ($raw) {
            $value = $field->fromDbValue($value);
        } else {
            $value = $field->cleanValue($value);
        }
        if ($this->metadata->isLocal($name)) {
            $this->data[$name] = $value;
        } elseif ($this->metadata->isVirtual($name)) {
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
        return isset($this->metadata->$name);
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
//        foreach ($this->relationDataCache as $key => $c) {
//            if ($c instanceof Model) {
//                dump($key, $c->toArray());
//            }
//        }
        return $this->data;
    }

    public function export()
    {
        $result = array();
        foreach ($this->metadata->getLocalFields() as $field) {
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
        foreach ($data as $k => $v) {
            if (isset($this->metadata->$k)) {
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

    /**
     *  for overriding
     */
    protected function init()
    {
    }
}