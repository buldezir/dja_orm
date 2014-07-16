<?php
/**
 * User: Alexander.Arutyunov
 * Date: 15.11.13
 * Time: 16:07
 */

namespace Dja\Db;

use Dja\Db\Model\Field\ForeignKey;
use Dja\Db\Model\Metadata;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Table;

/**
 * Class Creation
 * @package Dja\Db
 */
class Creation
{
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
     * @var bool
     */
    protected $followRelations = true;

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
     * ->processQueueCallback(function (\Dja\Db\Model\Metadata $metadata, \Doctrine\DBAL\Schema\Table $table, array $sql, \Doctrine\DBAL\Connection $db) {})
     * @param callable $callBack
     * @param bool $autoExecute
     */
    public function processQueueCallback(\Closure $callBack, $autoExecute = false)
    {
        $autoExecQueue = [];
        while (count($this->generateQueue)) {
            $modelName = array_shift($this->generateQueue);
            try {
                /** @var Metadata $metadata */
                $metadata = $modelName::metadata();
                if ($this->db->getSchemaManager()->tablesExist($metadata->getDbTableName())) {
                    continue;
                }
                $table = $this->metadataToTable($metadata);
                $sql = $this->dp->getCreateTableSQL($table, AbstractPlatform::CREATE_INDEXES | AbstractPlatform::CREATE_FOREIGNKEYS);
                if ($autoExecute) {
                    $autoExecQueue = array_merge($sql, $autoExecQueue);
                }
                $callBack($metadata, $table, $sql, $this->db);
            } catch (\Exception $e) {
            }
        }
        if ($autoExecute) {
            foreach ($autoExecQueue as $sql) {
                $this->db->exec($sql);
            }
        }
    }

    /**
     * @param Metadata $metadata
     * @return Table
     */
    public function metadataToTable(Metadata $metadata)
    {
        $tblName = $metadata->getDbTableName();
        if (isset($this->generated[$tblName])) {
            return $this->generated[$tblName];
        }
        $cols = [];
        foreach ($metadata->getLocalFields() as $fieldObj) {
            $col = $fieldObj->getDoctrineColumn();
            $col->setLength($fieldObj->max_length);
            $col->setNotnull(!$fieldObj->null);
            $col->setComment($fieldObj->help_text);
            $col->setAutoincrement($fieldObj->auto_increment);
            $cols[] = $col;
        }
        $table = new Table($tblName, $cols);
        $this->generated[$tblName] = $table;
        foreach ($metadata->getLocalFields() as $fieldObj) {
            if ($fieldObj->unique) {
                $table->addUniqueIndex([$fieldObj->db_column]);
            } elseif ($fieldObj->db_index) {
                $table->addIndex([$fieldObj->db_column]);
            }
            if ($fieldObj->primary_key) {
                $table->setPrimaryKey([$fieldObj->db_column]);
            }
            if ($this->followRelations === true && $fieldObj->isRelation() && $fieldObj instanceof ForeignKey) {
                $relationClass = $fieldObj->relationClass;
                $relactionTable = $this->metadataToTable($relationClass::metadata());
                $table->addForeignKeyConstraint($relactionTable, [$fieldObj->db_column], [$fieldObj->to_field]);
                $this->generateQueue[] = $relationClass;
            }
        }
        return $table;
    }

    /**
     * @param boolean $followRelations
     */
    public function setFollowRelations($followRelations)
    {
        $this->followRelations = (bool)$followRelations;
    }
}