<?php
/**
 * User: Alexander.Arutyunov
 * Date: 12.07.13
 * Time: 13:11
 */

namespace Dja\Db\Model\Field;

use Dja\Db\Model\Metadata;

/**
 * Class Base
 * @package Dja\Db\Model\Field
 *
 * @property string $name
 * @property mixed $default
 * @property string $db_column
 * @property bool $is_null
 * @property bool $is_unique
 * @property array $choices
 *
 * @property \Dja\Db\Model\Model $ownerClass
 * @property \Dja\Db\Model\Model $relationClass
 *
 *
 */
class Base
{
    /**
     * @var array
     */
    protected $_options = array(
        'name'         => null,
        'ownerClass'   => null,
        'relationClass'=> null,
        'type'         => null,
        'primary_key'  => false,
        'db_column'    => null,
        'db_index'     => false,
        'max_length'   => null,
        'is_null'      => false,
        'is_unique'    => false,
        'choices'      => null,
        'required'     => false,
        'default'      => null,
        'editable'     => true,
        'help_text'    => '',
        'verbose_name' => null,
        'using'        => null,
        'auto_increment'=> false
    );

    /**
     * @var Metadata
     */
    protected $metadata;

    /**
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $this->setOptions($options);
    }

    /**
     * for overriding
     */
    public function init()
    {
        if ($this->db_column === null) {
            $this->setOption('db_column', $this->name);
        }
    }

    /**
     * @param Metadata $metadata
     */
    public function setMetadata(Metadata $metadata)
    {
        $this->metadata = $metadata;
    }

    /**
     * return value for displaying, for example integer timestamp as datetime string
     * @param mixed $value
     * @return mixed
     */
    public function viewValue($value)
    {
        if ($this->choices) {
            if (isset($this->choices[$value])) {
                return $this->choices[$value];
            }
        }
        return $value;
    }

    /**
     * php representation of value
     * @param $value
     * @return mixed
     */
    public function cleanValue($value)
    {
        return $value;
    }

    /**
     * value stored in db
     * @param $value
     * @return mixed
     */
    public function dbPrepValue($value)
    {
        return $value;
    }

    /**
     * can Query use auto join (selectRelated) for this field
     * @return bool
     */
    public function canAutoJoin()
    {
        return ($this instanceof SingleRelation);
    }

    /**
     * if this field is relation object
     * @return bool
     */
    public function isRelation()
    {
        return false;
    }

    /**
     * validate input data for this field type
     * @param $value
     * @return bool
     */
    public function isValid($value)
    {
        return true;
    }

    /**
     * @param $value
     * @throws \Exception
     */
    public function validate($value)
    {
        if (!$this->isValid($value)) {
            throw new \Exception('Value is not valid');
        }
    }

    /**
     * return field-specific default value
     * @return mixed
     */
    public function getDefault()
    {
        return $this->getOption('default');
    }

    /**
     * easy access to options
     * @param $key
     * @return mixed
     */
    public function __get($key)
    {
        switch ($key) {
            case 'default':
                return $this->getDefault();
            default:
                return $this->getOption($key);
        }
    }

    /**
     * @param $key
     * @return bool
     */
    public function __isset($key)
    {
        return $this->issetOption($key);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function issetOption($key)
    {
        return array_key_exists($key, $this->_options);
    }

    /**
     * @param string $key
     * @return mixed
     * @throws \Exception
     */
    public function getOption($key)
    {
        if ($this->issetOption($key)) {
            return $this->_options[$key];
        }
        throw new \Exception('No such option!');
    }

    /**
     * @param string $key
     * @param $value
     * @return $this
     * @throws \Exception
     */
    public function setOption($key, $value)
    {
        if ($this->issetOption($key)) {
            $this->_options[$key] = $value;
        } else {
            throw new \Exception('No such option!');
        }
        return $this;
    }

    /**
     * @param array $options
     * @return $this
     */
    public function setOptions($options)
    {
        foreach ($options as $key => $value) {
            $this->setOption($key, $value);
        }
        return $this;
    }
}