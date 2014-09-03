<?php

namespace Dja\Util;

/**
 *  classy abstract singleton with late state binding
 * Class Singleton
 * @package Dja\Util
 */
abstract class Singleton
{
    protected static $_instCache = array();

    /**
     * @return static
     */
    public static function getInstance()
    {
        $class = get_called_class();
        if (!isset(self::$_instCache[$class])) {
            self::$_instCache[$class] = new $class;
        }
        return self::$_instCache[$class];
    }
}