<?php

namespace Dja\Db\Model\Field;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;

/**
 * Class Float
 * @package Dja\Db\Model\Field
 */
class Float extends Base
{
    public function __construct(array $options = array())
    {
        $this->_options['precision'] = 10;
        parent::__construct($options);
    }

    /**
     * @return \Doctrine\DBAL\Schema\Column
     */
    public function getDoctrineColumn()
    {
        return new Column($this->db_column, Type::getType(Type::FLOAT));
    }

    public function getPhpType()
    {
        return 'float';
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