<?php
/**
 * User: Alexander.Arutyunov
 * Date: 12.07.13
 * Time: 13:11
 */

namespace My\Model\Field;

class Int extends Base
{
    public function __construct(array $options = array())
    {
        $this->_options['max_length'] = 11;
        parent::__construct($options);
    }

    public function isValid($value)
    {
        if (intval($value) == $value) {
            return true;
        }
        return false;
    }
}