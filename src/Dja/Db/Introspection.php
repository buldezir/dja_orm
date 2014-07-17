<?php

namespace Dja\Db;

use Dja\Util\Inflector;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;

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
        Type::INTEGER => 'Int',
        Type::BIGINT => 'Int',
        Type::SMALLINT => 'Int',
        Type::DECIMAL => 'Float',
        Type::FLOAT => 'Float',
        Type::STRING => 'Char',
        Type::TEXT => 'Text',
        Type::DATETIME => 'DateTime',
        Type::DATE => 'Date',
        Type::TIME => 'Time',
        Type::BOOLEAN => 'Bool',
        Type::JSON_ARRAY => 'Json',
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
    protected $namespace;

    /**
     * @var string
     */
    protected $baseClass = '\Dja\Db\Model\Model';

    /**
     * @param \Doctrine\DBAL\Connection $db
     * @param array $generateQueue
     */
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
        while (count($this->generateQueue)) {
            $tbl = array_shift($this->generateQueue);
            if (!isset($this->generated[$tbl])) {
                try {
                    $code = $this->generateClassForTable($tbl);
                    $callBack($tbl, $this->table2model($tbl), $code);
                } catch (\Exception $e) {
                }
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
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * @param $tableName
     * @return string
     */
    public function generateClassForTable($tableName)
    {
        $table = $this->db->getSchemaManager()->listTableDetails($tableName);
        //$sqls = $this->db->getDatabasePlatform()->getCreateTableSQL($table);

        $colConfArray = [];

        $code = 'class ' . $this->table2model($table->getName()) . ' extends ' . $this->baseClass . PHP_EOL;
        $code .= '{' . PHP_EOL;
        $code .= '    protected static $dbtable = \'' . $table->getName() . '\';' . PHP_EOL;
        $code .= '    protected static $fields = [' . PHP_EOL;
        foreach ($table->getColumns() as $col) {
            $colConfArray[] = $colConf = $this->colConfig($col);
            $code .= '    ' . $this->colConfigDump($colConf) . PHP_EOL;
        }
        $code .= '    ];' . PHP_EOL;
        $code .= '}' . PHP_EOL;

        $nsCode = $this->namespace ? 'namespace ' . $this->namespace . ';' . PHP_EOL : '';
        $code = $nsCode . PHP_EOL . $this->classDoc($table, $colConfArray) . PHP_EOL . $code;

        return $code;
    }

    /**
     * @param $tname
     * @return string
     */
    protected function table2model($tname)
    {
        return $this->prefix . Inflector::classify($tname);
    }

    protected function classDoc(Table $table, array $colConfArray)
    {
        $s = '/**' . PHP_EOL;
        foreach ($colConfArray as $v) {
            $s .= ' * @property mixed $' . $v[0] . PHP_EOL;
        }
        $s .= ' */';
        return $s;
    }

    /**
     * @param array $colConf
     * @return string
     */
    protected function colConfigDump(array $colConf)
    {
        return '    \'' . $colConf[0] . '\' => [' . $this->dumpArray($colConf[1]) . '],';
    }

    /**
     * @param Column $col
     * @return array
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
        return [$fieldName, $conf];
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
     * @param string $namespace
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * @param string $prefix
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * @param string $fullNameWithNameSpace
     * @throws \InvalidArgumentException
     */
    public function setBaseClass($fullNameWithNameSpace)
    {
        if (!class_exists($fullNameWithNameSpace)) {
            throw new \InvalidArgumentException("Cannot extend {$fullNameWithNameSpace}");
        }
        $this->baseClass = $fullNameWithNameSpace;
    }
}