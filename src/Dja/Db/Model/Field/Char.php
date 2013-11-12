<?php
/**
 * User: Alexander.Arutyunov
 * Date: 12.07.13
 * Time: 13:11
 */

namespace Dja\Db\Model\Field;

/**
 * Class Char
 * @package Dja\Db\Model\Field
 *
 * varchar field
 */
class Char extends Base
{
    public function __construct(array $options = array())
    {
        $this->_options['max_length'] = 255;
        parent::__construct($options);
    }

    public function isValid($value)
    {
        if (is_string($value) && strlen($value) > $this->getOption('max_length')) { // todo: may be mb_strlen ?
            return true;
        }
        return false;
    }

    public function validate($value)
    {
        $msgs = array();
        if (!is_string($value)) {
            $msgs[] = 'Value is not string';
        } else {
            if (strlen($value) > $this->getOption('max_length')) {
                $msgs[] = 'Value length is > '.$this->getOption('max_length');
            }
        }
        if (count($msgs) > 0) {
            throw new \Dja\Db\Model\ValidationError($msgs);
        }
    }
}