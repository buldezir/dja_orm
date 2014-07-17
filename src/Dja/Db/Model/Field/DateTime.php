<?php
/**
 * User: Alexander.Arutyunov
 * Date: 15.07.13
 * Time: 12:06
 */

namespace Dja\Db\Model\Field;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;

/**
 * Class DateTime
 * @package Dja\Db\Model\Field
 *
 * datetime for mysql, timestamp for postgresql
 *
 * @property bool $autoInsert  set current time when inserting new row
 * @property bool $autoUpdate  set current time when updating row
 */
class DateTime extends Base
{
    protected $re = '#^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$#';
    protected $format = 'Y-m-d H:i:s';

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
        return new Column($this->db_column, Type::getType(Type::DATETIME));
    }

    public function getPhpType()
    {
        return 'string';
    }

    public function init()
    {
        $this->attachEvents();
        parent::init();
    }

    public function getDefault()
    {
        return date($this->format);
    }

    public function toPhp($value)
    {
        if (is_string($value)) {
            return $value;
        } elseif ($value instanceof \DateTime) {
            return $value->format($this->format);
        }
        return strval($value);
    }

    public function validate($value)
    {
        parent::validate($value);
        if (!preg_match($this->re, $value)) {
            throw new \InvalidArgumentException("Field '{$this->name}' must be in valid date-time format");
        }
    }

    protected function attachEvents()
    {
        if ($this->autoInsert || $this->autoUpdate) {
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
}