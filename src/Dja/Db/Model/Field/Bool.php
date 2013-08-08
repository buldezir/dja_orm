<?php
/**
 * User: Alexander.Arutyunov
 * Date: 12.07.13
 * Time: 13:11
 */

namespace Dja\Db\Model\Field;

class Bool extends Base
{
    public function __construct(array $options = array())
    {
        parent::__construct($options);
    }

    /**
     * @param $value
     * @return bool
     */
    public function cleanValue($value)
    {
        if (is_bool($value)) {
            return $value;
        }
        switch ($value) {
            case 't':
            case 'True':
            case '1':
                return true;
            case 'f':
            case 'False':
            case '0':
                return false;
        }
    }

    /**
     * @param $value
     * @return mixed
     */
    public function dbPrepValue($value)
    {
        return $value ? '1' : '0';
    }
}