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

    protected function _setupBackwardsRelation()
    {
        $ownerClass = $this->getOption('ownerClass');
        $remoteClass = $this->getOption('relationClass');
        if (!$remoteClass::metadata()->__isset($this->related_name)) {
            $options = array(
                'OneToOne',
                'relationClass' => $ownerClass,
                'to_field' => $this->db_column,
                'noBackwards' => true,
            );
            $remoteClass::metadata()->addField($this->related_name, $options);
        }
    }
}