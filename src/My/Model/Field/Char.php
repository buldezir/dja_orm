<?php
/**
 * User: Alexander.Arutyunov
 * Date: 12.07.13
 * Time: 13:11
 */

namespace My\Model\Field;

class Char extends Base
{
    public function __construct(array $options = array())
    {
        $this->_options['max_length'] = 255;
        parent::__construct($options);
    }

    public function isValid($value)
    {
        if (is_string($value) && strlen($value) <= $this->getOption('max_length')) {
            return true;
        }
        return false;
    }
}