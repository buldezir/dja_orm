<?php

namespace Dja\Db\Model\Field;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;

/**
 * Class Time
 * @package Dja\Db\Model\Field
 *
 * time for mysql, time for postgresql
 *
 * @property bool $autoInsert  set current time when inserting new row
 * @property bool $autoUpdate  set current time when updating row
 */
class Time extends DateTime
{
    protected $re = '#^\d{2}:\d{2}:\d{2}$#';
    protected $format = 'H:i:s';

    /**
     * @return \Doctrine\DBAL\Schema\Column
     */
    public function getDoctrineColumn()
    {
        return new Column($this->db_column, Type::getType(Type::TIME));
    }
}