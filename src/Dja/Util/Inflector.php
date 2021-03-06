<?php

namespace Dja\Util;

/**
 * Class Inflector
 * @package Dja\Util
 */
class Inflector
{
    public static $excludeAppWords = ['App', 'Application', 'Acme'];

    /**
     * Convert word in to the format for a Doctrine table name. Converts 'ModelName' to 'model_name'
     *
     * @param  string $word Word to tableize
     * @return string $word  Tableized word
     */
    public static function tableize($word)
    {
        $word = strrchr($word, '\\');
        $word = ltrim($word, '\\');
        return strtolower(preg_replace('~(?<=\\w)([A-Z])~', '_$1', $word));
    }

    /**
     * Convert application model names to db table names:
     * app/models.php namespace:\App\Models class:SomeModelName -> app_some_model_name
     * app/models/ClassName.php namespace:\App\Models class:ClassName -> app_some_model_name
     * @param $word
     * @return string
     */
    public static function namespacedTableize($word)
    {
        $a = explode('\\', $word);
        $modelPart = array_pop($a);
        $modelWord = array_pop($a);
        $appPart = array_pop($a);
        if ($modelWord == 'Models' || $modelWord == 'Model') {
            if ($appPart && !in_array($appPart, static::$excludeAppWords)) {
                $modelPart = $appPart . $modelPart;
            }
        }
        return strtolower(preg_replace('~(?<=\\w)([A-Z])~', '_$1', $modelPart));
    }

    /**
     * Convert a word in to the format for a Doctrine class name. Converts 'table_name' to 'TableName'
     *
     * @param string $word Word to classify
     * @return string $word  Classified word
     */
    public static function classify($word)
    {
        return str_replace(" ", "", ucwords(strtr($word, "_-", "  ")));
    }

    /**
     * Camelize a word. This uses the classify() method and turns the first character to lowercase
     * Converts 'my_table_name' to 'myTableName'
     *
     * @param string $word
     * @return string $word
     */
    public static function camelize($word)
    {
        return lcfirst(self::classify($word));
    }
}