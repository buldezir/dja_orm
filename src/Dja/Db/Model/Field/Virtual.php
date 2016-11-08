<?php

namespace Dja\Db\Model\Field;

use Dja\Db\Model\Model;

/**
 * Class Virtual
 * @package Dja\Db\Model\Field
 *
 * @property \Closure $getter
 *
 */
class Virtual extends Base
{
    public function __construct(array $options = [])
    {
        $this->_options['getter'] = null;

        parent::__construct($options);
    }

    /**
     * @return null
     */
    public function getDoctrineColumn()
    {
        return null;
    }

    public function init()
    {
        if (!$this->getter) {
            throw new \Exception('Must provide "getter" option');
        }
    }

    /**
     * @param Model $model
     * @return mixed
     */
    public function getProcessedValue(Model $model)
    {
        return call_user_func($this->getter, $model);
    }
}