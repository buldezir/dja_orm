<?php
/**
 * User: Alexander.Arutyunov
 * Date: 04.12.13
 * Time: 14:53
 */


namespace Dja\Db\Model\Field;

/**
 * Class Json
 * @package Dja\Db\Model\Field
 *
 * text field
 */
class Json extends Base
{
    public function toPhp($value)
    {
        if (null === $value) {
            return null;
        }
        return (array) $value;
    }

    public function fromDbValue($value)
    {
        if (null === $value) {
            return [];
        }
        $value = (is_resource($value)) ? stream_get_contents($value) : $value;
        return json_decode($value, true);
    }


    public function dbPrepValue($value)
    {
        if (null === $value) {
            return null;
        }
        return json_encode($value);
    }


    public function validate($value)
    {
        parent::validate($value);
        if (!is_array($value)) {
            throw new \InvalidArgumentException("Field '{$this->name}' must be array");
        }
    }
}