<?php

namespace Dja\Db\Model;

/**
 * Class Expr
 * @package Dja\Db\Model
 */
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