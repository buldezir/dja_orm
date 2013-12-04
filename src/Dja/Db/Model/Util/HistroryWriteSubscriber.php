<?php
/**
 * User: Alexander.Arutyunov
 * Date: 29.11.13
 * Time: 15:43
 */

namespace Dja\Db\Model\Util;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent as Event;

class HistroryWriteSubscriber implements EventSubscriberInterface
{
    protected $historyModelName;
    protected $isDeletable = true;

    public function __construct($historyModelName, $isDeletable = true)
    {
        $this->historyModelName = $historyModelName;
        $this->isDeletable = $isDeletable;
    }

    public function onAfterSave(Event $event)
    {
        /** @var \Dja\Db\Model\Model $model */
        $model = $event->getSubject();
        var_dump('save history for: ' . $model, $model->getChangedValues());

        $hClass = $this->historyModelName;
        /** @var \Dja\Db\Model\Metadata $historyMD */
        $historyMD = $hClass::metadata();
        //array_intersect(array_keys($historyMD->getLocalFields()), array_keys($model::metadata()->getLocalFields()));
        $hModel = new $hClass($model->getChangedValues());
    }

    public function onBeforeDelete(Event $event)
    {
        if ($this->isDeletable === false) {
            /** @var \Dja\Db\Model\Model $model */
            $model = $event->getSubject();
            echo 'write is_deleted';
            $model->is_cc_sent = 1;
            $model->save();
            $event->stopPropagation();
            return false;
        }
    }

    public function onAfterDelete(Event $event)
    {
        /** @var \Dja\Db\Model\Model $model */
        $model = $event->getSubject();
        var_dump('deleting all history for: ' . $model);
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return [
            \Dja\Db\Model\Model::EVENT_AFTER_SAVE => 'onAfterSave',
            \Dja\Db\Model\Model::EVENT_BEFORE_DELETE => 'onBeforeDelete',
            \Dja\Db\Model\Model::EVENT_AFTER_DELETE => 'onAfterDelete',
        ];
    }
}