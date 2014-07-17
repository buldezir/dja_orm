<?php

namespace Dja\Db\Model\Field;

/**
 * Class Ip
 * @package Dja\Db\Model\Field
 *
 * varchar field
 */
class Ip extends Char
{
    public function __construct(array $options = array())
    {
        $this->_options['max_length'] = 64;

        parent::__construct($options);
    }

    public function validate($value)
    {
        parent::validate($value);
        if (!filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
            throw new \InvalidArgumentException("Field '{$this->name}' must be in valid IPv4 or IPv6 format");
        }
    }
}