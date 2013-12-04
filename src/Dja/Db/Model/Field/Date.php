<?php
/**
 * User: Alexander.Arutyunov
 * Date: 19.11.13
 * Time: 17:22
 */

namespace Dja\Db\Model\Field;

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
    protected $re = '#^\d{4}-\d{2}-\d{2}$#';
    protected $format = 'Y-m-d';
}