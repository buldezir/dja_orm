<?php
/**
 * User: Alexander.Arutyunov
 * Date: 12.12.13
 * Time: 16:45
 */

namespace Dja\Db\Model\Field;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;

/**
 * Class NullBool
 * @package Dja\Db\Model\Field
 *
 * tinyint for mysql, bool for postgresql
 */
class NullBool extends Base
{
    /**
     * @return \Doctrine\DBAL\Schema\Column
     */
    public function getDoctrineColumn()
    {
        return new Column($this->db_column, Type::getType(Type::BOOLEAN));
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
            case '':
            case null:
                return null;
            case 't':
            case 'True':
            case '1':
                return true;
            case 'f':
            case 'False':
            case '0':
                return false;
            default:
                throw new \InvalidArgumentException("Cant convert value for field '{$this->name}' to boolean or null");
        }
    }

    /**
     * @param $value
     * @return mixed
     */
    public function dbPrepValue($value)
    {
        if (null === $value) {
            return null;
        } else {
            return $value ? '1' : '0';
        }
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