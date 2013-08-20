<?php
/**
 * User: Alexander.Arutyunov
 * Date: 30.07.13
 * Time: 14:09
 */

namespace Dja\Db\Model\Field;

/**
 * Class ForeignKey
 * @package Dja\Db\Model\Field
 *
 * @property string $to_field  field name in rel table
 * @property string $related_name  name for backwards relation
 */
class ForeignKey extends Base implements SingleRelation
{
    public function __construct(array $options = array())
    {
        $this->_options['related_name'] = null; // this is name of virtual field of relationClass which link to this model or modelquery
        $this->_options['to_field'] = null;
        $this->_options['noBackwards'] = false;

        parent::__construct($options);

        $this->setOption('db_index', true); // always index this col (?)
    }

    public function init()
    {
        if ($this->to_field === null) {
            /** @var \Dja\Db\Model\Model $relationClass */
            $relationClass = $this->relationClass;
            $this->setOption('to_field', $relationClass::metadata()->getPrimaryKey());
        }
        if ($this->db_column === null) {
            $this->setOption('db_column', $this->name . '_id');
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
        if (method_exists($model, '__toString')) {
            return strval($model);
        }
        if (isset($model->name)) {
            return $model->name;
        }
        return $model->pk;
    }

    public function isValid($value)
    {
        return ((is_object($value) && $value instanceof \Dja\Db\Model\Model) || intval($value) > 0);
    }

    public function isRelation()
    {
        return true;
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