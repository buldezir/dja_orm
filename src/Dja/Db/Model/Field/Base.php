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
 * @property bool $null
 * @property bool $unique
 * @property array $choices
 * @property int $max_length
 * @property
 *
 * @property \Dja\Db\Model\Model $ownerClass
 * @property \Dja\Db\Model\Model $relationClass
 *
 *
 */
abstract class Base
{
    /**
     * @var array
     */
    protected $_options = [
        'name' => null,
        'primary_key' => false,
        'db_column' => null,
        'db_index' => false,
        'max_length' => null,
        'blank' => true,
        'null' => false,
        'unique' => false,
        'choices' => null,
        'default' => null,
        'editable' => true,
        'help_text' => '',
        'verbose_name' => null,
        'using' => null,
        'auto_increment' => false,
        'ownerClass' => null,
    ];

    /**
     * @var Metadata
     */
    protected $metadata;

    /**
     * @var array
     */
    protected $validators = [];

    /**
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->setOptions($options);
    }

    /**
     * @return \Doctrine\DBAL\Schema\Column
     */
    abstract public function getDoctrineColumn();

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
     * user input or untrusted source
     * php representation of value
     * +
     * validation
     * @param $value
     * @return mixed
     */
    public function cleanValue($value)
    {
        $value = $this->toPhp($value);
        $this->validate($value);
        return $value;
    }

    /**
     * user input or untrusted source
     * php representation of value
     * @param $value
     * @return mixed
     */
    public function toPhp($value)
    {
        return $value;
    }

    /**
     * db -> php
     * @param $value
     * @return mixed
     */
    public function fromDbValue($value)
    {
        return $value;
    }

    /**
     * php -> db
     * value stored in db
     * @param $value
     * @return mixed
     */
    public function dbPrepValue($value)
    {
        return $value;
    }

    /**
     * for where conditions
     * @param $value
     * @return mixed
     */
    public function dbPrepLookup($value)
    {
        if (null !== $value) {
            $value = $this->dbPrepValue($value);
        }
        return $value;
    }

    /**
     * @param $value
     * @throws \Exception
     */
    public function validate($value)
    {
        if (!$this->editable) {
            return;
        }
        if (!$this->null && $value === null) {
            throw new \InvalidArgumentException("Field '{$this->name}' is not nullable");
        }
        if (!$this->blank && empty($value)) {
            throw new \InvalidArgumentException("Field '{$this->name}' cannot be empty");
        }
        foreach ($this->validators as &$validator) {
            call_user_func_array($validator, [$value, $this]);
        }
    }

    /**
     * @param $value
     * @return bool
     */
    public function isValid($value)
    {
        try {
            $this->validate($value);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * call with args ($value, $this)
     * @param callable $v
     * @return $this
     */
    public function addValidator(\Closure $v)
    {
        $this->validators[] = $v;
        return $this;
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
        throw new \Exception("No '{$key}' option!");
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
            throw new \Exception("No '{$key}' option!");
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