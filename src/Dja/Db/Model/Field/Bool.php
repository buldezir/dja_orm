<?php
/**
 * User: Alexander.Arutyunov
 * Date: 12.07.13
 * Time: 13:11
 */

namespace Dja\Db\Model\Field;

class Bool extends Base
{
    public function __construct(array $options = array())
    {
        parent::__construct($options);
    }

    public function cleanValue($value)
    {
        return (bool) $value;
    }


}