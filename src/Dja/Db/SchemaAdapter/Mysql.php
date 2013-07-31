<?php
/**
 * User: Alexander.Arutyunov
 * Date: 11.07.13
 * Time: 14:16
 */

namespace Dja\Db\SchemaAdapter;

use Dja\Db\Schema;

class Mysql extends Schema
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
    );

    public function getTables()
    {
        return $this->db->query("select table_name from information_schema.tables where table_schema = 'public'")->fetchAll(\PDO::FETCH_COLUMN, 0);
    }

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

    public function getColumns($table)
    {
        $q = $this->db->placeHold("
        select column_name, ordinal_position, column_default, is_nullable, character_maximum_length, udt_name
        from information_schema.columns where table_schema = 'public' and table_name = ?
        ", $table);
        $data = $this->db->query($q)->fetchAll();
        $result = array();
        foreach ($data as $row) {
            $result[] = array(
                'pos' => $row['ordinal_position'],
                'name' => $row['column_name'],
                'is_null' => $row['is_nullable'] == 'YES',
                'max_length' => $row['character_maximum_length'],
                'type' => $this->udtNameToFieldType($row['udt_name']),
                'default' => $row['column_default'],
            );
        }
        return $result;
    }

    public function escapeSchema($value)
    {
        if (strpos($value, '.') !== false) {
            return '`'.implode('`.`', explode('.', $value)).'`';
        } else {
            return '`'.$value.'`';
        }
    }

    public function escapeValue($value)
    {
        return $this->db->quote($value);
    }

}