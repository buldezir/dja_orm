<?php

namespace Dja\Db\Model\Lookup;

/**
 * Class Postgresql
 * @package Dja\Db\Model\Lookup
 */
class Postgresql extends LookupAbstract
{
    public function lookupYear(&$escapedField, &$rawValue, &$negate)
    {
        return $this->getDateTimePartLookup($escapedField, 'year');
    }

    public function lookupMonth(&$escapedField, &$rawValue, &$negate)
    {
        return $this->getDateTimePartLookup($escapedField, 'month');
    }

    public function lookupDay(&$escapedField, &$rawValue, &$negate)
    {
        return $this->getDateTimePartLookup($escapedField, 'day');
    }

    public function lookupWeekDay(&$escapedField, &$rawValue, &$negate)
    {
        /*
         * php have 2 formats:
         *  0 - sunday, 6 - saturday (dow acceps this format)
         *  1 - monday, 7 - sunday
         */
        if ($rawValue == 7) {
            $rawValue = 0;
        }
        return $this->getDateTimePartLookup($escapedField, 'dow');
    }

    public function lookupHour(&$escapedField, &$rawValue, &$negate)
    {
        return $this->getDateTimePartLookup($escapedField, 'hour');
    }

    public function lookupMinute(&$escapedField, &$rawValue, &$negate)
    {
        return $this->getDateTimePartLookup($escapedField, 'minute');
    }

    public function lookupSecond(&$escapedField, &$rawValue, &$negate)
    {
        return $this->getDateTimePartLookup($escapedField, 'second');
    }

    public function getDateTimePartLookup(&$escapedField, $type)
    {
        $escapedFieldCopy = $escapedField;
        $escapedField = '';
        return sprintf("date_part('%s', %s) = %%s", $type, $escapedFieldCopy);
    }
}