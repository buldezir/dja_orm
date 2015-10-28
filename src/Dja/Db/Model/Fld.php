<?php

namespace Dja\Db\Model;

/**
 * Class Fld
 * @package Dja\Db\Model
 */
class Fld
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