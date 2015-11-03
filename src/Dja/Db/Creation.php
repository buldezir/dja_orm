<?php

namespace Dja\Db;

use Dja\Db\Model\Field\ForeignKey;
use Dja\Db\Model\Field\ManyToMany;
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
    public $md2tableCache = [];

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
     * @param callable|\Closure $callBack
     */
    public function processQueueCallback(\Closure $callBack)
    {
        $callbackQueue = [];
        while (count($this->generateQueue)) {
            $modelName = array_shift($this->generateQueue);
            try {
                /** @var Metadata $metadata */
                $metadata = $modelName::metadata();
                $tblName = $metadata->getDbTableName();
                if ($this->db->getSchemaManager()->tablesExist($tblName)) {
                    continue;
                }
                if (isset($this->generated[$tblName])) {
                    continue;
                }
                $table = $this->metadataToTable($metadata);
                $this->generated[$tblName] = 1;
                $sql = $this->dp->getCreateTableSQL($table, AbstractPlatform::CREATE_INDEXES);
                array_unshift($callbackQueue, [$metadata, $table, $sql]);
                $fks = $table->getForeignKeys();
                if (count($fks)) {
                    $sql = [];
                    foreach ($fks as $fk) {
                        $sql[] = $this->dp->getCreateForeignKeySQL($fk, $table);
                    }
                    array_push($callbackQueue, [$metadata, $table, $sql]);
                }
            } catch (\Exception $e) {
                pr($e->__toString());
            }
        }
        foreach ($callbackQueue as $args) {
            $callBack($args[0], $args[1], $args[2], $this->db);
        }
    }

    /**
     * @param Metadata $metadata
     * @return Table
     */
    public function metadataToTable(Metadata $metadata)
    {
        $tblName = $metadata->getDbTableName();
        if (isset($this->md2tableCache[$tblName])) {
            return $this->md2tableCache[$tblName];
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
        $this->md2tableCache[$tblName] = $table;
        foreach ($metadata->getLocalFields() as $fieldObj) {
            if ($fieldObj->unique) {
                $table->addUniqueIndex([$fieldObj->db_column]);
            } elseif ($fieldObj->db_index) {
                $table->addIndex([$fieldObj->db_column]);
            }
            if ($fieldObj->primary_key) {
                $table->setPrimaryKey([$fieldObj->db_column]);
            }
            if ($this->followRelations === true && $fieldObj instanceof ForeignKey) {
                $relationClass = $fieldObj->relationClass;
                $relationTable = $this->metadataToTable($relationClass::metadata());
                $table->addForeignKeyConstraint($relationTable, [$fieldObj->db_column], [$fieldObj->to_field]);
                $this->generateQueue[] = $relationClass;
            }
        }
        if ($this->followRelations === true) {
            foreach ($metadata->getRelationFields() as $fieldObj) {
                if ($fieldObj instanceof ManyToMany) {
                    if ($fieldObj->throughClass) {
                        $throughClass = $fieldObj->throughClass;
                        //$this->metadataToTable($throughClass::metadata());
                        $this->generateQueue[] = $throughClass;
                    }
                }
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