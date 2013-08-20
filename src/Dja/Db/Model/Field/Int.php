<?php
/**
 * User: Alexander.Arutyunov
 * Date: 12.07.13
 * Time: 13:11
 */

namespace Dja\Db\Model\Field;

/**
 * Class Int
 * @package Dja\Db\Model\Field
 *
 */
class Int extends Base
{
    public function __construct(array $options = array())
    {
        $this->_options['max_length'] = 11;
        parent::__construct($options);
    }

    public function isValid($value)
    {
        if (strval(intval($value)) === strval($value)) { // may be this is wtf
            return true;
        }
        return false;
    }
}