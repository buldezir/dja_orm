<?php
/**
 * User: Alexander.Arutyunov
 * Date: 30.07.13
 * Time: 14:18
 */

namespace Dja\Db\Model\Field;

interface ManyRelation
{
    public function getRelQuery(\Dja\Db\Model\Model $model);
}