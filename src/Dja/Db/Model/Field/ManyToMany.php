<?php

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
        $this->_options['limit_choices_to'] = null;
        $this->_options['throughClass'] = null;
        $this->_options['db_table'] = null;

        parent::__construct($options);

        $this->setOption('db_column', false);
        $this->setOption('editable', false);

        /*if (!$this->to_field) {
            throw new \Exception('"to_field" is required option for Dja\\Db\\Model\\Field\\ManyToOne');
            // may be it shoud be set equal to pk field name
        }*/
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
        if ($this->limit_choices_to !== null && !is_array($this->limit_choices_to)) {
            throw new \Exception('"limit_choices_to" must be argument array for model::objects()->filter()');
        }
        if ($this->to_field === null) {
            $this->setOption('to_field', $this->getRelationMetadata()->getPrimaryKey());
        }
        if ($this->self_field === null) {
            $this->setOption('self_field', $this->metadata->getPrimaryKey());
        }
        if ($this->related_name === null) {
            $this->setOption('related_name', $this->metadata->getDbTableName() . '_set');
        }
        if (!$this->throughClass && !$this->db_table) {
            //throw new \Exception('Must provide "throughClass" or "db_table" option');
            $this->_setUpThroughClass();
        }
        if ($this->throughClass) {
            $throughClass = $this->throughClass;
            $this->setOption('db_table', $throughClass::metadata()->getDbTableName());
        }
        if (!$this->noBackwards) {
            $this->_setupBackwardsRelation();
        }
    }

    protected function _setUpThroughClass()
    {
        $throughClassName = $this->ownerClass . 'To' . $this->relationClass;
        $self_field = $this->metadata->getDbTableName();
        $to_field = $this->getRelationMetadata()->getDbTableName();
        //$db_table = $this->metadata->getDbTableName() . '_to_' . $this->getRelationMetadata()->getDbTableName();
        $db_table = $self_field . '_to_' . $to_field;
        $fkClassName = ForeignKey::class;
        $classCode = "
        class {$throughClassName} extends \\Dja\\Db\\Model\\Model
        {
            protected static \$dbtable = '{$db_table}';
            protected static \$fields = [
                '{$self_field}' => ['{$fkClassName}', 'relationClass' => '{$this->ownerClass}', 'db_column' => '{$this->self_field}'],
                '{$to_field}' => ['{$fkClassName}', 'relationClass' => '{$this->relationClass}', 'db_column' => '{$this->to_field}'],
            ];
        }
        ";
        eval($classCode);
        $this->db_table = $db_table;
        $this->throughClass = $throughClassName;
        $throughClassName::metadata();
    }

    /**
     * modify related model metadata to setup virtual field pointing to this model queryset
     */
    protected function _setupBackwardsRelation()
    {
        if ($this->getRelationMetadata()->__isset($this->related_name)) {
            return;
        }
        $ownerClass = $this->getOption('ownerClass');
        if ($this->throughClass) {
            $options = array(
                ManyToMany::class,
                'relationClass' => $ownerClass,
                'self_field' => $this->to_field,
                'to_field' => $this->self_field,
                'throughClass' => $this->throughClass,
                'noBackwards' => true,
            );
        } else {
            $options = array(
                ManyToMany::class,
                'relationClass' => $ownerClass,
                'self_field' => $this->to_field,
                'to_field' => $this->self_field,
                'db_table' => $this->db_table,
                'noBackwards' => true,
            );
        }
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
            return $relationClass::objects()->relation($model, $this)->filter($filter);
        } else {
            return $relationClass::objects()->relation($model, $this)->filter([$this->to_field . '__in' => $in]);
        }
    }
}