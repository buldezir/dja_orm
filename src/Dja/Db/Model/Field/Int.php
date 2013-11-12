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

    public function toPhp($value)
    {
        return intval($value);
    }

    public function validate($value)
    {
        parent::validate($value);
        if (!is_int($value)) {
            throw new \InvalidArgumentException("Field '{$this->name}' must be integer");
        }
    }


}