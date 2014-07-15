<?php
/**
 * User: Alexander.Arutyunov
 * Date: 02.08.13
 * Time: 13:09
 */

namespace Dja\Db\Model\Field;

/**
 * Class ManyToMany
 * @package Dja\Db\Model\Field
 *
 * @property string $to_field  field name in rel table
 * @property string $self_field  field name in self table
 * @property string $related_name  name for backwards relation
 * @property array $limit_choices_to  filter for rel queryset
 * @property string $db_table  name of table for storing relations, if throughClass not defined
 * @property \Dja\Db\Model\Model $throughClass  model for storing relations
 *
 */
class ManyToMany extends Relation implements ManyToManyRelation
{
    public function __construct(array $options = [])
    {
        $this->_options['self_field'] = null;
        $this->_options['db_table'] = null;
        $this->_options['limit_choices_to'] = null;
        $this->_options['throughClass'] = null;

        parent::__construct($options);

        $this->setOption('db_column', false);
        $this->setOption('editable', false);

        /*if (!$this->to_field) {
            throw new \Exception('"to_field" is required option for Dja\\Db\\Model\\Field\\ManyToOne');
            // may be it shoud be set equal to pk field name
        }*/
        if ($this->limit_choices_to !== null && !is_array($this->limit_choices_to)) {
            throw new \Exception('"limit_choices_to" must be argument array for model::objects()->filter()');
        }
        if (!$this->throughClass && !$this->db_table) {
            throw new \Exception('Must provide "throughClass" or "db_table" option');
        }
    }

    public function init()
    {
        if ($this->to_field === null) {
            $this->setOption('to_field', $this->getRelationMetadata()->getPrimaryKey());
        }
        if ($this->self_field === null) {
            $this->setOption('self_field', $this->metadata->getPrimaryKey());
        }
        if ($this->related_name === null) {
            $this->setOption('related_name', $this->metadata->getDbTableName() . '_set');
        }
        if ($this->throughClass) {
            $throughClass = $this->throughClass;
            $this->setOption('db_table', $throughClass::metadata()->getDbTableName());
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
        $options = array(
            'ManyToMany',
            'relationClass' => $ownerClass,
            'self_field' => $this->to_field,
            'to_field' => $this->self_field,
            'db_table' => $this->db_table,
            'noBackwards' => true,
        );
        $this->getRelationMetadata()->addField($this->related_name, $options);
    }

    /**
     * @param \Dja\Db\Model\Model $model
     * @return \Dja\Db\Model\Query\QuerySet
     */
    public function getRelation(\Dja\Db\Model\Model $model)
    {
        /** @var \Dja\Db\Model\Model $relationClass */
        $relationClass = $this->relationClass;
        /** @var \Dja\Db\Model\Model $throughClass */
        $throughClass = $this->throughClass;

        if ($throughClass) {
            $in = $throughClass::objects()->filter([$this->self_field => $model->__get($this->self_field)])->valuesList($this->to_field, false);
        } else {
            $sql = sprintf('SELECT "%s" FROM "%s" WHERE "%s" = %d', $this->to_field, $this->db_table, $this->self_field, $model->__get($this->self_field));
            //$in = $relationClass::objects()->setRawSql($sql)->valuesDict($this->to_field, $this->to_field);
            $in = Expr($sql);
        }


        if ($this->limit_choices_to) {
            $filter = $this->limit_choices_to;
            $filter[$this->to_field . '__in'] = $in;
            return $relationClass::objects()->filter($filter);
        } else {
            return $relationClass::objects()->filter([$this->to_field . '__in' => $in]);
        }
    }
}