<?php

namespace Dja\Db\Model\Field;

/**
 * Class Slug
 * @package Dja\Db\Model\Field
 *
 * varchar field
 */
class Slug extends Char
{
    public function __construct(array $options = array())
    {
        $this->_options['max_length'] = 64;
        $this->_options['prepopulate_field'] = null;

        parent::__construct($options);
    }

    public function init()
    {
        $this->attachEvents();
        parent::init();
    }

    public function toPhp($value)
    {
        $value = parent::toPhp($value);
        return slugify($value); // move "slugify" to this class?
    }

    protected function attachEvents()
    {
        if ($this->prepopulate_field) {
            $class = $this->metadata->getModelClass();
            $field = $this;
            $this->metadata->events()->addListener($class::EVENT_BEFORE_SAVE, function (\Symfony\Component\EventDispatcher\GenericEvent $event) use ($field) {
                /** @var \Dja\Db\Model\Model $model */
                $model = $event->getSubject();
                $fieldName = $field->db_column;
                $ppFieldName = $field->prepopulate_field;
                if (empty($model->$fieldName)) {
                    $model->$fieldName = $model->$ppFieldName;
                }
            });
        }
    }
}