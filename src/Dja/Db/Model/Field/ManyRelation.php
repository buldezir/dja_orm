<?php
/**
 * User: Alexander.Arutyunov
 * Date: 30.07.13
 * Time: 14:18
 */

namespace Dja\Db\Model\Field;

interface ManyRelation
{
    /**
     * @param \Dja\Db\Model\Model $model
     * @return \Dja\Db\Model\Query\QuerySet
     */
    public function getRelation(\Dja\Db\Model\Model $model);
}