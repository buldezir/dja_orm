<?php
/**
 * User: Alexander.Arutyunov
 */

namespace Dja\Db\Model;

class Expr
{
    public $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    function __toString()
    {
        return $this->value;
    }
}