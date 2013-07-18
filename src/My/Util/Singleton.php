<?php
/**
 * User: Alexander.Arutyunov
 * Date: 10.07.13
 * Time: 13:25
 */
namespace My\Util;

abstract class Singleton
{
    protected static $_instCache = array();

    public static function getInstance()
    {
        $class = get_called_class();
        if (!isset(self::$_instCache[$class])) {
            self::$_instCache[$class] = new $class;
        }
        return self::$_instCache[$class];
    }
}