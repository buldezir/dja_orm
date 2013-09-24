<?php
/**
 * User: Alexander.Arutyunov
 * Date: 29.08.13
 * Time: 12:53
 */

namespace Dja\Db\Model;

use Exception;

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