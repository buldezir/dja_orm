<?php
/**
 * User: Alexander.Arutyunov
 * Date: 12.11.13
 * Time: 11:36
 */

namespace Dja\Db\Model\Field;

/**
 * Class Char
 * @package Dja\Db\Model\Field
 *
 * varchar field
 */
class Email extends Char
{
    /**
     * @var string
     */
    protected $re = "#^[a-zA-Z0-9.!\#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$#";

    public function isValid($value)
    {
        if (parent::isValid($value) && preg_match($this->re, $value)) {
            return true;
        }
        return false;
    }

    public function validate($value)
    {
        parent::validate($value);
        $msgs = array();
        if (!preg_match($this->re, $value)) {
            $msgs[] = '"'.$value.'" is not a valid email';
        }
        if (count($msgs) > 0) {
            throw new \Dja\Db\Model\ValidationError($msgs);
        }
    }
}