<?php

namespace Dja\Db\Model\Query;

use Dja\Db\Model\Field\ManyToManyRelation;
use Dja\Db\Model\Field\Relation;
use Dja\Db\Model\Field\SingleRelation;
use Dja\Db\Model\Metadata;
use Dja\Db\Model\Model;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Class RelationQuerySet
 * @package Dja\Db\Model\Query
 */
class RelationQuerySet extends QuerySet
{
    /**
     * @var Model
     */
    protected $ownerModel;

    /**
     * @var Relation
     */
    protected $ownerField;

    /**
     * @param Metadata $metadata
     * @param QueryBuilder $qb
     * @param Connection $db
     * @param Model $ownerModel
     * @param Relation $ownerField
     */
    public function __construct(Metadata $metadata, QueryBuilder $qb = null, Connection $db = null, Model $ownerModel, Relation $ownerField)
    {
        parent::__construct($metadata, $qb, $db);
        $this->ownerModel = $ownerModel;
        $this->ownerField = $ownerField;
    }

    /**
     * @param Model $model
     * @return $this
     * @throws \BadMethodCallException
     */
    public function add(Model $model)
    {
        /** @var Model[] $list */
        $list = func_get_args();
        if ($this->ownerField instanceof SingleRelation) {
            foreach ($list as $model) {
                $model->__set($this->ownerField->to_field, $this->ownerModel);
                $model->save();
            }
        } elseif ($this->ownerField instanceof ManyToManyRelation) {
            // @todo m2m link
        } else {
            throw new \BadMethodCallException('Cant modify query set for field ' . $this->ownerField->name);
        }
        $this->resetStatement();
        return $this;
    }

    /**
     * @param Model $model
     * @return $this
     * @throws \BadMethodCallException
     */
    public function remove(Model $model)
    {
        /** @var Model[] $list */
        $list = func_get_args();
        if ($this->ownerField instanceof SingleRelation) {
            foreach ($list as $model) {
                $model->__unset($this->ownerField->to_field);
                $model->save();
            }
        } elseif ($this->ownerField instanceof ManyToManyRelation) {
            // @todo m2m unlink
        } else {
            throw new \BadMethodCallException('Cant modify query set for field ' . $this->ownerField->name);
        }
        $this->resetStatement();
        return $this;
    }
}