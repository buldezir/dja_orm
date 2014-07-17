<?php

namespace Dja\Db\Model\Field;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;

/**
 * Class Decimal
 * @package Dja\Db\Model\Field
 */
class Decimal extends Base
{
    public function __construct(array $options = array())
    {
        parent::__construct($options);
    }

    /**
     * @return \Doctrine\DBAL\Schema\Column
     */
    public function getDoctrineColumn()
    {
        return new Column($this->db_column, Type::getType(Type::DECIMAL), ['precision' => $this->precision, 'scale' => $this->scale]);
    }

    public function getPhpType()
    {
        return 'float';
    }

    public function init()
    {
        if (!$this->precision || !$this->scale) {
            throw new \Exception('Must provide "precision" and "scale" option');
        }
        parent::init();
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
            throw new \InvalidArgumentException("Field '{$this->name}' must be float/decimal");
        }
    }
}