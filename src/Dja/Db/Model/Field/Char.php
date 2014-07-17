<?php

namespace Dja\Db\Model\Field;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;

/**
 * Class Char
 * @package Dja\Db\Model\Field
 *
 * varchar field
 */
class Char extends Base
{
    public function __construct(array $options = array())
    {
        $this->_options['max_length'] = 255;
        parent::__construct($options);
    }

    /**
     * @return \Doctrine\DBAL\Schema\Column
     */
    public function getDoctrineColumn()
    {
        return new Column($this->db_column, Type::getType(Type::STRING));
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
        if (strlen($value) > $this->getOption('max_length')) {
            throw new \InvalidArgumentException("Field '{$this->name}' value length > $this->max_length");
        }
    }
}