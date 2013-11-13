<?php
/**
 * User: Alexander.Arutyunov
 * Date: 13.11.13
 * Time: 11:35
 */

namespace Dja\Db\Model\Field;

/**
 * Class Int
 * @package Dja\Db\Model\Field
 *
 */
class Float extends Base
{
    public function __construct(array $options = array())
    {
        $this->_options['max_length'] = 11;
        parent::__construct($options);
    }

    public function toPhp($value)
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }
        return floatval($value);
    }

    public function validate($value)
    {
        parent::validate($value);
        if (!is_float($value)) {
            throw new \InvalidArgumentException("Field '{$this->name}' must be float");
        }
    }
}