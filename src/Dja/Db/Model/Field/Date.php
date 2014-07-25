<?php

namespace Dja\Db\Model\Field;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;

/**
 * Class Date
 * @package Dja\Db\Model\Field
 *
 * date for mysql, date for postgresql
 *
 * @property bool $autoInsert  set current time when inserting new row
 * @property bool $autoUpdate  set current time when updating row
 */
class Date extends DateTime
{
    const FORMAT = 'Y-m-d';
    const REGEX = '#^\d{4}-\d{2}-\d{2}$#';

    /**
     * @return \Doctrine\DBAL\Schema\Column
     */
    public function getDoctrineColumn()
    {
        return new Column($this->db_column, Type::getType(Type::DATE));
    }
}