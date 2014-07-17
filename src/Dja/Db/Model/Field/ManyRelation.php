<?php

namespace Dja\Db\Model\Field;

/**
 * Interface ManyRelation
 * @package Dja\Db\Model\Field
 */
interface ManyRelation
{
    /**
     * @param \Dja\Db\Model\Model $model
     * @return \Dja\Db\Model\Query\QuerySet
     */
    public function getRelation(\Dja\Db\Model\Model $model);
}