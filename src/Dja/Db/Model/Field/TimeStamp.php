<?php

namespace Dja\Db\Model\Field;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;

/**
 * Class TimeStamp
 * @package Dja\Db\Model\Field
 *
 * @property bool $autoUpdate
 * @property bool $autoInsert
 * @property
 */
class TimeStamp extends Base
{
    public function __construct(array $options = array())
    {
        $this->_options['autoInsert'] = false;
        $this->_options['autoUpdate'] = false;

        parent::__construct($options);

    }

    /**
     * @return \Doctrine\DBAL\Schema\Column
     */
    public function getDoctrineColumn()
    {
        return new Column($this->db_column, Type::getType(Type::INTEGER), ['unsigned' => true]);
    }

    public function init()
    {
        $this->attachEvents();
        parent::init();
    }

    public function getDefault()
    {
        return time();
    }

    public function toPhp($value)
    {
        if (is_int($value)) {
            return new \DateTime('@' . $value);
        } elseif ($value instanceof \DateTime) {
            return $value;
        } else {
            return new \DateTime($value);
        }
    }

    public function validate($value)
    {
        parent::validate($value);
        if (!$value instanceof \DateTime) {
            throw new \InvalidArgumentException("Field '{$this->name}' must be instanceof \\DateTime");
        }
    }

    public function fromDbValue($value)
    {
        return new \DateTime('@' . $value);
    }

    public function dbPrepValue(\DateTime $value)
    {
        return $value->getTimestamp();
    }

    protected function attachEvents()
    {
        $class = $this->ownerClass;
        $field = $this;
        $this->metadata->events()->addListener($class::EVENT_BEFORE_SAVE, function (\Symfony\Component\EventDispatcher\GenericEvent $event) use ($field) {
            /** @var \Dja\Db\Model\Model $model */
            $model = $event->getSubject();
            $fieldName = $field->db_column;
            if ($field->autoInsert === true) {
                if ($model->isNewRecord()) {
                    $model->$fieldName = $field->getDefault();
                }
            }
            if ($field->autoUpdate === true) {
                if (!$model->isNewRecord()) {
                    $model->$fieldName = $field->getDefault();
                }
            }
        });
    }
}