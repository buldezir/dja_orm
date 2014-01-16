<?php
/**
 * User: Alexander.Arutyunov
 * Date: 30.07.13
 * Time: 14:09
 */

namespace Dja\Db\Model\Field;

use Dja\Db\Model\Model;

/**
 * Class ForeignKey
 * @package Dja\Db\Model\Field
 *
 * @property string $to_field  field name in rel table
 * @property string $related_name  name for backwards relation
 */
class ForeignKey extends Relation implements SingleRelation
{
    public function __construct(array $options = array())
    {
        parent::__construct($options);

        $this->setOption('db_index', true); // always index this col (?)
    }

    public function init()
    {
        if ($this->to_field === null) {
            $this->setOption('to_field', $this->getRelationMetadata()->getPrimaryKey());
        }
        if ($this->db_column === null) {
            $dbcol = strpos($this->name, '_id' !== false) ? $this->name : $this->name . '_id';
            $this->setOption('db_column', $dbcol);
        }
        if ($this->related_name === null) {
            $this->setOption('related_name', $this->metadata->getDbTableName() . '_set');
        }
        if (!$this->noBackwards) {
            $this->_setupBackwardsRelation();
        }
    }

    /**
     * modify related model metadata to setup virtual field pointing to this model queryset
     */
    protected function _setupBackwardsRelation()
    {
        $ownerClass = $this->getOption('ownerClass');
        $relationClass = $this->getOption('relationClass');
        $options = array(
            'ManyToOne',
            'relationClass' => $ownerClass,
            'self_field' => $this->to_field,
            'to_field' => $this->db_column,
            'noBackwards' => true,
        );
        $relationClass::metadata()->addField($this->related_name, $options);
    }

    /**
     * @param \Dja\Db\Model\Model $model
     * @return int|mixed|string
     */
    public function viewValue($model)
    {
        if (is_object($model)) {
            if (method_exists($model, '__toString')) {
                return strval($model);
            }
            if (isset($model->name)) {
                return $model->name;
            }
            return $model->pk;
        } else {
            return $model;
        }
    }

    /**
     * @param $value
     * @throws \InvalidArgumentException
     */
    public function validate($value)
    {
        parent::validate($value);

        if (is_int($value)) {
            if ($value <= 0) {
                throw new \InvalidArgumentException("Field '{$this->name}' must be integer > 0");
            }
        } elseif (is_object($value)) {
            if (!$value instanceof \Dja\Db\Model\Model) {
                throw new \InvalidArgumentException("Field '{$this->name}' must be instance of \\Dja\\Db\\Model\\Model");
            }
            if (get_class($value) !== $this->relationClass) {
                throw new \InvalidArgumentException("Field '{$this->name}' must be instance of {$this->relationClass}");
            }
        } else {
            throw new \InvalidArgumentException("Field '{$this->name}' must be integer or object");
        }
    }

    /**
     * @param $value
     * @return int|mixed
     */
    public function dbPrepValue($value)
    {
        if ($value instanceof Model) {
            return intval($value->__get($this->to_field));
        } else {
            //return intval($value);
            return $value;
        }
    }

    /**
     * @param \Dja\Db\Model\Model $model
     * @return \Dja\Db\Model\Model
     */
    public function getRelation(\Dja\Db\Model\Model $model)
    {
        /** @var \Dja\Db\Model\Model $relationClass */
        $relationClass = $this->relationClass;
        $value = $model->__get($this->db_column);
        if (!empty($value)) {
            $inst = $relationClass::objects()->filter([$this->to_field => (int)$value])->current();
            return $inst;
        } else {
            return $this->default;
        }
    }
}