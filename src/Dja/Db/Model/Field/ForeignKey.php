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
        $this->_options['related_name'] = null;
        $this->_options['to_field']     = null;

        $this->setOption('db_index', true);

        parent::__construct($options);

        if (empty($this->db_column)) {
            $this->setOption('db_column', $this->name.'_id');
        }
    }

    public function init()
    {
        if (empty($this->to_field)) {
            /** @var \Dja\Db\Model\Model $relationClass */
            $relationClass = $this->relationClass;
            $this->setOption('to_field', $relationClass::metadata()->getPrimaryKey());
        }
    }

    protected function _setupBackwardsRelation()
    {
        if (!$this->related_name) {
            //throw new \Exception('"related_name" is required option for ForeignKey');
            return;
        }
        $ownerClass = $this->getOption('ownerClass');
        $remoteClass = $this->getOption('relationClass');
        $related_name = $this->related_name;
        $options = array(
            'Dja_Db_Model_Field_ManyToOne',
            'relationClass' => $ownerClass,
            'selfColumn' => $this->to_field,
            'refColumn'  => $this->db_column
        );
        $remoteClass::metadata()->addField($related_name, $options);
    }

    public function isRelation()
    {
        return true;
    }

    public function getRelObject($value)
    {
        /** @var \Dja\Db\Model\Model $relationClass */
        $relationClass = $this->relationClass;
        if (!empty($value)) {
            $inst = $relationClass::objects()->filter([$this->to_field => (int)$value])->current();
            return $inst;
        } else {
            return $this->default;
        }
    }
}