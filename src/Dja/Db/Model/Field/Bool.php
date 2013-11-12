<?php
/**
 * User: Alexander.Arutyunov
 * Date: 12.07.13
 * Time: 13:11
 */

namespace Dja\Db\Model\Field;

/**
 * Class Bool
 * @package Dja\Db\Model\Field
 *
 * tinyint for mysql, bool for postgresql
 */
class Bool extends Base
{
    public function __construct(array $options = array())
    {
        parent::__construct($options);
    }

    /**
     * @param $value
     * @return bool
     * @throws \Exception
     */
    public function toPhp($value)
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
            default:
                throw new \InvalidArgumentException("Cant convert value for field '{$this->name}' to boolean");
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

    /**
     * @param $value
     * @return bool
     */
    public function fromDbValue($value)
    {
        return $this->toPhp($value);
    }

    /**
     * @param $value
     * @throws \Exception
     */
    public function validate($value)
    {
        parent::validate($value);
        if (!is_bool($value)) {
            throw new \InvalidArgumentException("Field '{$this->name}' must be boolean");
        }
    }
}