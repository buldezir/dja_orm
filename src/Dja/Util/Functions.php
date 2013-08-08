<?php
/**
 * User: Alexander.Arutyunov
 * Date: 27.07.13
 * Time: 15:35
 */

/**
 * @param $shortName
 * @return string
 */
function controller($shortName)
{
    list($shortClass, $shortMethod) = explode('/', $shortName, 2);
    return sprintf('App\Controllers\%sController::%sAction', ucfirst($shortClass), $shortMethod);
}

/**
 * @param $longName
 * @return string
 */
function controller_reverse($longName)
{
    return lcfirst(preg_replace('#^App\\\Controllers\\\(\w+)Controller::(\w+)Action$#', '$1/$2', $longName));
}

/**
 * @param $args
 * @return array
 */
function wildcard($args)
{
    if (substr($args, -1) === '/') {
        $args = substr($args, 0, -1);
    }
    return explode('/', $args);
}

/**
 * @param $path
 * @return mixed
 */
function import($path)
{
    if ('\\' === $path{0}) {
        $path = substr($path, 1);
    }
    $parts = strpos($path, '.') === false ? explode('\\', $path) : explode('.', $path);
    if (strtolower($parts[0]) === 'app') {
        $prefix = DJA_APP_PATH.DIRECTORY_SEPARATOR;
        foreach ($parts as $i => $part) {
            $parts[$i] = lcfirst($part);
        }
        $parts[$i] = ucfirst($parts[$i]);
    } else {
        $prefix = DJA_PATH.DIRECTORY_SEPARATOR;
        foreach ($parts as $i => $part) {
            $parts[$i] = ucfirst($part);
        }
    }
    unset($parts[0]);
    $path = $prefix.implode(DIRECTORY_SEPARATOR, $parts).'.php';
    return require($path);
}

/**
 * @return string
 */
function __()
{
   return '[reserved for translate]';
}

/**
 * @return string
 */
function pr()
{
    $s = array();
    $vars = func_get_args();
    foreach ($vars as $v) {
        ob_start();
        var_dump($v);
        $s[] = '<p>'.ob_get_clean().'</p>';
    }
    echo implode('', $s);
}

/**
 * @return string
 */
function dumps()
{
    $s = array();
    $vars = func_get_args();
    foreach ($vars as $v) {
        ob_start();
        var_dump($v);
        $s[] = ob_get_clean();
    }
    return '<pre>'.implode('<hr>', $s).'</pre>';
}

/**
 *
 */
function dump()
{
    echo call_user_func_array('dumps', func_get_args());
}

/**
 * @param $value
 * @return \Dja\Db\Model\Expr
 */
function Expr($value)
{
    return new Dja\Db\Model\Expr($value);
}