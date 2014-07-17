<?php

namespace Dja\Db\Model\Lookup;

/**
 * Class Postgresql
 * @package Dja\Db\Model\Lookup
 */
class Postgresql extends LookupAbstract
{
    protected $operators = array(
        'exact' => '= %s',
        'in' => 'IN (%s)',
        'isnull' => 'IS NULL',
        'isnotnull' => 'IS NOT NULL',
        'iexact' => 'LIKE %s',
        'contains' => 'LIKE BINARY %s',
        'icontains' => 'LIKE %s',
        'regex' => 'REGEXP BINARY %s',
        'iregex' => 'REGEXP %s',
        'gt' => '> %s',
        'gte' => '>= %s',
        'lt' => '< %s',
        'lte' => '<= %s',
        'startswith' => 'LIKE BINARY %s',
        'endswith' => 'LIKE BINARY %s',
        'istartswith' => 'LIKE %s',
        'iendswith' => 'LIKE %s',
        'raw' => '%s',
        'range' => 'BETWEEN %s',
    );

    protected function udtNameToFieldType($v)
    {
        switch ($v) {
            case 'int2':
            case 'int4':
                return self::TYPE_INT;
                break;
            case 'varchar':
            case 'timestamp':
                return self::TYPE_STRING;
                break;
            case 'bool':
                return self::TYPE_BOOL;
                break;
            default:
                return self::TYPE_STRING;
                break;
        }
    }
}