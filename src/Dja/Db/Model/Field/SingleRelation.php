<?php

namespace Dja\Db\Model\Field;

/**
 * Interface SingleRelation
 * @package Dja\Db\Model\Field
 */
interface SingleRelation
{
    /**
     * @param \Dja\Db\Model\Model $model
     * @return \Dja\Db\Model\Model
     */
    public function getRelation(\Dja\Db\Model\Model $model);
}