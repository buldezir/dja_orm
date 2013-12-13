<?php
/**
 * User: Alexander.Arutyunov
 * Date: 01.08.13
 * Time: 15:46
 */

namespace Dja\Db\Model\Field;

class OneToOne extends ForeignKey implements SingleRelation
{
    public function init()
    {
        if (empty($this->related_name)) {
            $this->setOption('related_name', $this->metadata->getDbTableName());
        }
        parent::init();
    }

    /**
     * modify related model metadata to setup virtual field pointing to this model
     */
    protected function _setupBackwardsRelation()
    {
        $ownerClass = $this->getOption('ownerClass');
        if (!$this->getRelationMetadata()->__isset($this->related_name)) {
            $options = array(
                'OneToOne',
                'relationClass' => $ownerClass,
                'to_field' => $this->db_column,
                'noBackwards' => true,
            );
            $this->getRelationMetadata()->addField($this->related_name, $options);
        }
    }
}