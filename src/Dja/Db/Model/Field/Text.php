<?php

namespace Dja\Db\Model\Field;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;

/**
 * Class Text
 * @package Dja\Db\Model\Field
 *
 * text field
 */
class Text extends Base
{
    /**
     * @return \Doctrine\DBAL\Schema\Column
     */
    public function getDoctrineColumn()
    {
        return new Column($this->db_column, Type::getType(Type::TEXT));
    }

    public function getPhpType()
    {
        return 'string';
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
    }
}