<?php
/**
 * User: Alexander.Arutyunov
 * Date: 15.07.13
 * Time: 12:06
 */

namespace My\Model\Field;

class DateTime extends Base
{
    public function __construct(array $options = array())
    {
        $this->_options['autoInsert'] = false;
        $this->_options['autoUpdate'] = false;

        parent::__construct($options);
    }

    public function init()
    {
        $this->attachEvents();
    }

    public function getDefault()
    {
        return date('Y-m-d H:i:s');
    }

    public function isValid($value)
    {
        if (preg_match('#^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$#', $value)) {
            return true;
        }
        return false;
    }

    protected function attachEvents()
    {
        $class = $this->ownerClass;
        $field = $this;
        $this->metadata->events()->addListener($class::EVENT_BEFORE_SAVE, function(\Symfony\Component\EventDispatcher\GenericEvent $event)use($field){
            /** @var \My\Model\Model $model */
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