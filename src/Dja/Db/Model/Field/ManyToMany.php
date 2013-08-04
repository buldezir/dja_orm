<?php
/**
 * User: Alexander.Arutyunov
 * Date: 02.08.13
 * Time: 13:09
 */

namespace Dja\Db\Model\Field;

class ManyToMany extends Base implements ManyToManyRelation
{
    public function __construct(array $options = array())
    {
        $this->_options['to_field']     = null;
        $this->_options['self_field']   = null;

        parent::__construct($options);

        $this->setOption('db_column', false);

        if (!$this->to_field) {
            throw new \Exception('"to_field" is required option for Dja\\Db\\Model\\Field\\ManyToOne');
            // may be it shoud be set equal to pk field name
        }
    }

    public function init()
    {

    }

    /**
     * @return bool
     */
    public function isRelation()
    {
        return true;
    }

    /**
     * @param \Dja\Db\Model\Model $model
     * @return \Dja\Db\Model\Query
     */
    public function getRelation(\Dja\Db\Model\Model $model)
    {
        /** @var \Dja\Db\Model\Model $relationClass */
        /*$relationClass = $this->relationClass;
        $selfField = $this->self_field;
        $value = $model->$selfField;
        if (!empty($value)) {
            $inst = $relationClass::objects()->setRelationFieldName($this->name)->filter([$this->to_field => (int)$value]);
            return $inst;
        } else {
            return new \ArrayIterator();
        }*/
    }
}