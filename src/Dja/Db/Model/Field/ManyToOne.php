<?php
/**
 * User: Alexander.Arutyunov
 * Date: 01.08.13
 * Time: 14:56
 */
namespace Dja\Db\Model\Field;

/**
 * basic usage - as auto backwards relation of ForeignKey
 *
 * Class ManyToOne
 * @package Dja\Db\Model\Field
 */
class ManyToOne extends Relation implements ManyRelation
{
    public function __construct(array $options = array())
    {
        $this->_options['self_field'] = null;

        parent::__construct($options);

        $this->setOption('db_column', false);
        $this->setOption('editable', false);
    }

    /**
     * @return null
     */
    public function getDoctrineColumn()
    {
        return null;
    }

    public function getPhpType()
    {
        return '\Dja\Db\Model\Query\RelationQuerySet|\Dja\Db\Model\Model[]';
    }

    public function init()
    {
        if (!$this->to_field) {
            throw new \Exception('"to_field" is required option for Dja\\Db\\Model\\Field\\ManyToOne');
            // may be it shoud be set equal to pk field name
        }

        if ($this->self_field === null) {
            $this->setOption('self_field', $this->metadata->getPrimaryKey());
        }
    }

    /**
     * @param \Dja\Db\Model\Model $model
     * @return \ArrayIterator|\Dja\Db\Model\Query\QuerySet
     */
    public function getRelation(\Dja\Db\Model\Model $model)
    {
        /** @var \Dja\Db\Model\Model $relationClass */
        $relationClass = $this->relationClass;
        $selfField = $this->self_field;
        $value = $model->$selfField;
        if (!empty($value)) {
            $inst = $relationClass::objects()->relation($model, $this)->filter([$this->to_field => (int)$value]);
            return $inst;
        } else {
            return new \ArrayIterator();
        }
    }
}