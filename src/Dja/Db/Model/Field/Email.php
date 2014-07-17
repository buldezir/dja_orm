<?php

namespace Dja\Db\Model\Field;

/**
 * Class Char
 * @package Dja\Db\Model\Field
 *
 * varchar field
 */
class Email extends Char
{
    /**
     * @var string
     */
    protected $re = "#^[a-zA-Z0-9.!\#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$#";

    public function __construct(array $options = array())
    {
        $this->_options['max_length'] = 64;

        parent::__construct($options);
    }

    public function validate($value)
    {
        parent::validate($value);
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            //if (!preg_match($this->re, $value)) {
            throw new \InvalidArgumentException("Field '{$this->name}' must be in valid email format");
        }
    }
}