<?php
/**
 * User: Alexander.Arutyunov
 * Date: 30.07.13
 * Time: 14:09
 */

namespace Dja\Db\Model\Field;

class ForeignKey extends Base implements SingleRelation
{
    public function __construct(array $options = array())
    {
        $this->_options['related_name'] = null; // this is name of virtual field of relationClass which link to this model or modelquery
        $this->_options['to_field'] = null;

        $this->setOption('db_index', true);

        parent::__construct($options);
    }

    public function init()
    {
        if (empty($this->to_field)) {
            /** @var \Dja\Db\Model\Model $relationClass */
            $relationClass = $this->relationClass;
            $this->setOption('to_field', $relationClass::metadata()->getPrimaryKey());
        }
        if (empty($this->db_column)) {
            $this->setOption('db_column', $this->name . '_id');
        }
        if (empty($this->related_name)) {
            $this->setOption('related_name', $this->metadata->getDbTableName() . '_set');
        }
        $this->_setupBackwardsRelation();
    }

    protected function _setupBackwardsRelation()
    {
//        if (!$this->related_name) {
//            //throw new \Exception('"related_name" is required option for ForeignKey');
//            return;
//        }
        $ownerClass = $this->getOption('ownerClass');
        $remoteClass = $this->getOption('relationClass');
        $options = array(
            'ManyToOne',
            'relationClass' => $ownerClass,
            'self_field' => $this->to_field,
            'to_field' => $this->db_column
        );
        $remoteClass::metadata()->addField($this->related_name, $options);
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
        $value = $model->__get($this->to_field);
        if (!empty($value)) {
            $inst = $relationClass::objects()->filter([$this->to_field => (int)$value])->current();
            return $inst;
        } else {
            return $this->default;
        }
    }
}