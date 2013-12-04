<?php
/**
 * User: Alexander.Arutyunov
 * Date: 19.11.13
 * Time: 17:29
 */

namespace Dja\Db\Model\Field;

/**
 * Class Text
 * @package Dja\Db\Model\Field
 *
 * text field
 */
class Text extends Char
{
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
    }
}