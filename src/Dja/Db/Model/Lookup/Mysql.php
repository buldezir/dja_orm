<?php

namespace Dja\Db\Model\Lookup;

/**
 * Class Mysql
 * @package Dja\Db\Model\Lookup
 */
class Mysql extends LookupAbstract
{
    public function lookupYear(&$escapedField, &$rawValue, &$negate)
    {
        return $this->getDateTimePartLookup($escapedField, 'YEAR');
    }

    public function lookupMonth(&$escapedField, &$rawValue, &$negate)
    {
        return $this->getDateTimePartLookup($escapedField, 'MONTH');
    }

    public function lookupDay(&$escapedField, &$rawValue, &$negate)
    {
        return $this->getDateTimePartLookup($escapedField, 'DAYOFMONTH');
    }

    public function lookupWeekDay(&$escapedField, &$rawValue, &$negate)
    {
        /*
         * php have 2 formats:
         *  0 - sunday, 6 - saturday
         *  1 - monday, 7 - sunday
         * DAYOFWEEK accepts:
         *  1 - sunday, 7 - saturday
         */
        if ($rawValue == 7) {
            $rawValue = 0;
        }
        $rawValue += 1;
        return $this->getDateTimePartLookup($escapedField, 'DAYOFWEEK');
    }

    public function lookupHour(&$escapedField, &$rawValue, &$negate)
    {
        return $this->getDateTimePartLookup($escapedField, 'HOUR');
    }

    public function lookupMinute(&$escapedField, &$rawValue, &$negate)
    {
        return $this->getDateTimePartLookup($escapedField, 'MINUTE');
    }

    public function lookupSecond(&$escapedField, &$rawValue, &$negate)
    {
        return $this->getDateTimePartLookup($escapedField, 'SECOND');
    }

    public function getDateTimePartLookup(&$escapedField, $typeFN)
    {
        $escapedFieldCopy = $escapedField;
        $escapedField = '';
        return sprintf("%s(%s) = %%s", $typeFN, $escapedFieldCopy);
    }
}