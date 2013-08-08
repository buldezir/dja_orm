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
        $this->_options['to_field'] = null;
        $this->_options['self_field'] = null;
        $this->_options['related_name'] = null;
        $this->_options['db_table'] = null;
        $this->_options['limit_choices_to'] = null;
        $this->_options['throughClass'] = null;
        $this->_options['noBackwards'] = false;

        parent::__construct($options);

        $this->setOption('db_column', false);

        /*if (!$this->to_field) {
            throw new \Exception('"to_field" is required option for Dja\\Db\\Model\\Field\\ManyToOne');
            // may be it shoud be set equal to pk field name
        }*/
        if ($this->limit_choices_to !== null && !is_array($this->limit_choices_to)) {
            throw new \Exception('"limit_choices_to" must be argument array for model::objects()->filter()');
        }
    }

    public function init()
    {
        if (empty($this->to_field)) {
            /** @var \Dja\Db\Model\Model $relationClass */
            $relationClass = $this->relationClass;
            $this->setOption('to_field', $relationClass::metadata()->getPrimaryKey());
        }
        if (empty($this->self_field)) {
            $this->setOption('self_field', $this->metadata->getPrimaryKey());
        }
        if (empty($this->related_name)) {
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

    protected function _setupBackwardsRelation()
    {
        $ownerClass = $this->getOption('ownerClass');
        $relationClass = $this->getOption('relationClass');
        $options = array(
            'ManyToMany',
            'relationClass' => $ownerClass,
            'self_field' => $this->to_field,
            'to_field' => $this->self_field,
            'db_table' => $this->db_table,
            'noBackwards' => true,
        );
        $relationClass::metadata()->addField($this->related_name, $options);
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
        /** @var \Dja\Db\Model\Model $throughClass */
        $throughClass = $this->throughClass;

        if ($throughClass) {
            $in = $throughClass::objects()->filter([$this->self_field => $model->__get($this->self_field)])->valuesDict($this->to_field, $this->to_field);
        } else {
            $sql = sprintf('SELECT "%s" FROM "%s" WHERE "%s" = %d', $this->to_field, $this->db_table, $this->self_field, $model->__get($this->self_field));
            //$in = $relationClass::objects()->setRawSql($sql)->valuesDict($this->to_field, $this->to_field);
            $in = Expr($sql);
        }

        if (count($in) > 0) {
            $inst = $relationClass::objects()->filter([$this->to_field . '__in' => $in]);
            if ($this->limit_choices_to) {
                $inst->filter($this->limit_choices_to);
            }
            return $inst;
        } else {
            return new \ArrayIterator();
        }
    }
}