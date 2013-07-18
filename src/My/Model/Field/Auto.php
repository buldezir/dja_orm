<?php
/**
 * User: Alexander.Arutyunov
 * Date: 12.07.13
 * Time: 13:11
 */

namespace My\Model\Field;

class Auto extends Int
{
    public function __construct(array $options = array())
    {
        $this->_options['primary_key'] = true;
        $this->_options['auto_increment'] = true;
        $this->_options['editable'] = false;
        parent::__construct($options);
    }
}