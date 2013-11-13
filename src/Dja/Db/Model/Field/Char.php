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

    public function toPhp($value)
    {
        return strval($value);
    }

    public function validate($value)
    {
        parent::validate($value);
        if (!is_string($value)) {
            throw new \InvalidArgumentException("Field '{$this->name}' must be string");
        }
        if (strlen($value) > $this->getOption('max_length')) {
            throw new \InvalidArgumentException("Field '{$this->name}' value length > $this->max_length");
        }
    }
}