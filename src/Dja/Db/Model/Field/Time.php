<?php
/**
 * User: Alexander.Arutyunov
 * Date: 04.12.13
 * Time: 14:38
 */


namespace Dja\Db\Model\Field;

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
}