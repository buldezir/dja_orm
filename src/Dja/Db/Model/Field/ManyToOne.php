<?php
/**
 * User: Alexander.Arutyunov
 * Date: 01.08.13
 * Time: 14:56
 */
namespace Dja\Db\Model\Field;

class ManyToOne extends Base implements ManyRelation
{
    public function __construct(array $options = array())
    {
        $this->_options['to_field'] = null;
        $this->_options['self_field'] = null;
        $this->_options['noBackwards'] = false;

        parent::__construct($options);

        $this->setOption('db_column', false);

        if (!$this->to_field) {
            throw new \Exception('"to_field" is required option for Dja\\Db\\Model\\Field\\ManyToOne');
            // may be it shoud be set equal to pk field name
        }
    }

    public function init()
    {
        if (empty($this->self_field)) {
            $this->setOption('self_field', $this->metadata->getPrimaryKey());
        }
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
        $relationClass = $this->relationClass;
        $selfField = $this->self_field;
        $value = $model->$selfField;
        if (!empty($value)) {
            $inst = $relationClass::objects()->setRelation([$this, $model])->filter([$this->to_field => (int)$value]);
            return $inst;
        } else {
            return new \ArrayIterator();
        }
    }
}