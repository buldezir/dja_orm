<?php

namespace Dja\Db\Model;

use Exception;

/**
 * Class ValidationError
 * @package Dja\Db\Model
 */
class ValidationError extends \Exception
{
    protected $messages = array();

    public function __construct(array $messages, $code = 0, Exception $previous = null)
    {
        $this->messages = $messages;
        parent::__construct(implode("; \n", $messages), $code, $previous);
    }

    public function getMessages()
    {
        return $this->messages;
    }
}