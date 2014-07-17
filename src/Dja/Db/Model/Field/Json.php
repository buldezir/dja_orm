<?php

namespace Dja\Db\Model\Field;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;

/**
 * Class Json
 * @package Dja\Db\Model\Field
 *
 * text field
 */
class Json extends Base
{
    /**
     * @return \Doctrine\DBAL\Schema\Column
     */
    public function getDoctrineColumn()
    {
        return new Column($this->db_column, Type::getType(Type::JSON_ARRAY));
    }

    public function toPhp($value)
    {
        if (null === $value) {
            return null;
        }
        return (array)$value;
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