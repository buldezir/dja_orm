<?php
/**
 * User: Alexander.Arutyunov
 * Date: 12.07.13
 * Time: 13:11
 */

namespace Dja\Db\Model\Field;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;

/**
 * Class Int
 * @package Dja\Db\Model\Field
 *
 */
class Int extends Base
{
    public function __construct(array $options = array())
    {
        $this->_options['max_length'] = 10;
        parent::__construct($options);
    }

    /**
     * @return \Doctrine\DBAL\Schema\Column
     */
    public function getDoctrineColumn()
    {
        $type = Type::INTEGER;
        if ($this->max_length <= 19) {
            $type = Type::BIGINT;
        }
        if ($this->max_length <= 10) {
            $type = Type::INTEGER;
        }
        if ($this->max_length <= 5) {
            $type = Type::SMALLINT;
        }
        return new Column($this->db_column, Type::getType($type));
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