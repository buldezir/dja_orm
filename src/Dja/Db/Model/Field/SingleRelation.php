<?php
/**
 * User: Alexander.Arutyunov
 * Date: 30.07.13
 * Time: 14:18
 */

namespace Dja\Db\Model\Field;

interface SingleRelation
{
    public function getRelObject($value);
}