<?php

namespace Dja\Db\Model\Query;

use Dja\Db\Model\Field\ManyToManyRelation;
use Dja\Db\Model\Field\Relation;
use Dja\Db\Model\Field\SingleRelation;
use Dja\Db\Model\Metadata;
use Dja\Db\Model\Model;
use Doctrine\DBAL\Connection;

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
    public function add($model)
    {
        /** @var Model[] $list */
        if ($model instanceof QuerySet) {
            $list = $model;
        } else {
            $list = func_get_args();
        }
        if ($this->ownerField instanceof SingleRelation) {
            foreach ($list as $model) {
                $model->__set($this->ownerField->to_field, $this->ownerModel);
                $model->save();
            }
        } elseif ($this->ownerField instanceof ManyToManyRelation) {
            if ($this->ownerField->throughClass) {
                $throughClass = $this->ownerField->throughClass;
                foreach ($list as $model) {
                    $model->save();
                    /** @var Model $link */
                    $link = new $throughClass;
                    $link->__set($this->ownerField->self_field, $this->ownerModel);
                    $link->__set($this->ownerField->to_field, $model);
                    $link->save();
                }
            } else {

            }
        } else {
            throw new \BadMethodCallException('Cant modify query set for field ' . $this->ownerField->name);
        }
        //$this->resetStatement();
        return $this;
    }

    /**
     * @param Model $model
     * @return $this
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function remove($model)
    {
        /** @var Model[] $list */
        if ($model instanceof QuerySet) {
            $list = $model;
        } else {
            $list = func_get_args();
        }
        if ($this->ownerField instanceof SingleRelation) {
            foreach ($list as $model) {
                if ($model->isNewRecord()) {
                    throw new \InvalidArgumentException('Cant remove record that is not saved');
                }
                $model->__unset($this->ownerField->to_field);
                $model->save();
            }
        } elseif ($this->ownerField instanceof ManyToManyRelation) {
            if ($this->ownerField->throughClass) {
                $throughClass = $this->ownerField->throughClass;
                $in = [];
                foreach ($list as $model) {
                    if ($model->isNewRecord()) {
                        throw new \InvalidArgumentException('Cant remove record that is not saved');
                    }
                    $in[] = $model->__get($this->ownerField->to_field);
                }
                foreach ($throughClass::objects()->filter([$this->ownerField->to_field . '__in' => $in]) as $link) {
                    $link->delete();
                }
            }
        } else {
            throw new \BadMethodCallException('Cant modify query set for field ' . $this->ownerField->name);
        }
        //$this->resetStatement();
        return $this;
    }
}