<?php

namespace Dja\Db\Model\Field;

/**
 * base class for all relation fields
 * Class Relation
 * @package Dja\Db\Model\Field
 *
 * @property string $to_field  field name in rel table
 * @property string $related_name  name for backwards relation
 */
abstract class Relation extends Base
{
    public function __construct(array $options = array())
    {
        $this->_options['relationClass'] = null;
        $this->_options['related_name'] = null; // this is name of virtual field of relationClass which link to this model or modelquery
        $this->_options['to_field'] = null;
        $this->_options['noBackwards'] = false;

        parent::__construct($options);
    }

    /**
     * @return bool
     */
    public function isRelation()
    {
        return true;
    }

    /**
     * @return \Dja\Db\Model\Metadata
     */
    public function getRelationMetadata()
    {
        $relationClass = $this->relationClass;
        return $relationClass::metadata();
    }

    abstract public function getRelation(\Dja\Db\Model\Model $model);
}