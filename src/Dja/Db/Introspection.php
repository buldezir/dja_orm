<?php
/**
 * User: Alexander.Arutyunov
 * Date: 15.11.13
 * Time: 16:07
 */

namespace Dja\Db;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;

/**
 * Class Introspection
 * @package Dja\Db
 *
 * usage:
 * $dbi = new Dja\Db\Introspection($dbConn, $dbConn->getSchemaManager()->listTableNames());
 * $dbi->setPrefix('Model');
 * $dbi->processQueueCallbackNoRel(function ($tableName, $modelClassName, $code) {
 *    file_put_contents(SOME_MODEL_DIR.$modelClassName.'.php', $code);
 * });
 */
class Introspection
{
    public static $dbType2FieldClass = [
        'integer' => 'Int',
        'bigint' => 'Int',
        'smallint' => 'Int',
        'decimal' => 'Float',
        'string' => 'Char',
        'text' => 'Text',
        'datetime' => 'DateTime',
        'date' => 'Date',
        'time' => 'Time',
        'boolean' => 'Bool',
    ];

    public $generateQueue = [];
    public $generated = [];

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $db;

    /**
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    protected $dp;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * @var string
     */
    protected $postfix;

    public function __construct(\Doctrine\DBAL\Connection $db, $generateQueue = [])
    {
        $this->db = $db;
        $this->dp = $db->getDatabasePlatform();
        $this->generateQueue = $generateQueue;
    }

    /**
     * will call with args ($tableName, $modelClassName, $code)
     * @param callable $callBack
     */
    public function processQueueCallback(\Closure $callBack)
    {
        while(count($this->generateQueue)) {
            $tbl = array_shift($this->generateQueue);
            if (!isset($this->generated[$tbl])) {
                try {
                    $code = $this->generateClassForTable($tbl);
                    $callBack($tbl, $this->table2model($tbl), $code);
                } catch (\Exception $e){}
                $this->generated[$tbl] = $tbl;
            }
        }
    }

    /**
     * will call with args ($tableName, $modelClassName, $code)
     * @param callable $callBack
     */
    public function processQueueCallbackNoRel(\Closure $callBack)
    {
        $q = $this->generateQueue;
        foreach ($q as $tbl) {
            try {
                $code = $this->generateClassForTable($tbl);
                $callBack($tbl, $this->table2model($tbl), $code);
            } catch (\Exception $e){}
        }
    }

    /**
     * @param $tableName
     * @return string
     */
    public function generateClassForTable($tableName)
    {
        $table = $this->db->getSchemaManager()->listTableDetails($tableName);
        //echo '<pre>'; print_r($tInfo);
        $sqls = $this->db->getDatabasePlatform()->getCreateTableSQL($table);
        //dump($sqls);

        $code = 'class ' . $this->table2model($table->getName()) . ' extends Dja\Db\Model\Model' . PHP_EOL;
        $code .= '{' . PHP_EOL;
        $code .= '    protected static $dbtable = \'' . $table->getName() . '\';' . PHP_EOL;
        $code .= '    protected static $fields = [' . PHP_EOL;
        foreach ($table->getColumns() as $col) {
            $code .= '    ' . $this->colConfig($col) . PHP_EOL;
        }
        $code .= '    ];' . PHP_EOL;
        $code .= '}' . PHP_EOL;

        return $code;
    }

    /**
     * @param $tname
     * @return string
     */
    protected function table2model($tname)
    {
        $s = $tname;
        $a = preg_split('/[- _]/', $s);
        $a = array_map('ucfirst', $a);
        return $this->prefix . implode('', $a) . $this->postfix;
    }

    /**
     * @param Column $col
     * @return string
     */
    protected function colConfig(Column $col)
    {
        $conf = [];
        //var_dump($col->toArray()); //, $col->getType()->getTypesMap());
        $fieldClass = self::$dbType2FieldClass[$col->getType()->getName()];
        $fieldName = $col->getName();
        if ($col->getAutoincrement()) {
            $fieldClass = 'Auto';
        } elseif (substr($col->getName(), -3) === '_id') {
            $fieldClass = 'ForeignKey';
            $fk_tbl = substr($col->getName(), 0, strpos($col->getName(), '_id'));
            $fieldName = $fk_tbl;
            $conf['relationClass'] = $this->table2model($fk_tbl);
            $conf['db_column'] = $col->getName();
            if (!isset($this->generated[$fk_tbl])) {
                $this->generateQueue[] = $fk_tbl;
            }
        }
        array_unshift($conf, $fieldClass);

        if ($this->dp->getReservedKeywordsList()->isKeyword($col->getName())) {
            $conf['db_column'] = 'f_' . $col->getName();
        }

        if ($col->getNotnull() === false) {
            $conf['null'] = true;
        }
        if ($col->getLength() !== null) {
            $conf['max_length'] = $col->getLength();
        }
        if ($col->getDefault() !== null) {
            $conf['default'] = $col->getType()->convertToPHPValue($col->getDefault(), $this->dp);
            if ($conf['default'] === '') {
                $conf['blank'] = true;
            }
        }
        if ($col->getComment() !== null) {
            $help = $col->getComment();
            if (strpos($help, PHP_EOL) !== false) {
                $help = str_replace(PHP_EOL, '', $help);
            }
            $conf['help_text'] = $help;
        }
        return '    \'' . $fieldName . '\' => [' . $this->dumpArray($conf) . '],';
    }

    /**
     * @param array $array
     * @return string
     */
    protected function dumpArray(array $array)
    {
        $a = [];
        foreach ($array as $k => $v) {
            $v = var_export($v, 1);
            if (!is_int($k)) {
                $a[] = "'$k' => $v";
            } else {
                $a[] = $v;
            }
        }
        return implode(', ', $a);
    }

    /**
     * @param mixed $postfix
     */
    public function setPostfix($postfix)
    {
        $this->postfix = $postfix;
    }

    /**
     * @param mixed $prefix
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }
}