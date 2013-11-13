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
    return array_filter(explode('/', $args));
}

/**
 * @param $path
 * @throws Exception
 * @return mixed
 */
function import($path)
{
    if ('\\' === $path{0}) {
        $path = substr($path, 1);
    }
    $parts = strpos($path, '.') === false ? explode('\\', $path) : explode('.', $path);
    if (strtolower($parts[0]) === 'app') {
        $prefix = DJA_APP_PATH . DIRECTORY_SEPARATOR;
        foreach ($parts as $i => $part) {
            $parts[$i] = lcfirst($part);
        }
        $parts[$i] = ucfirst($parts[$i]);
        unset($parts[0]);
    } elseif (strtolower($parts[0]) === 'dja') {
        $prefix = DJA_PATH . DIRECTORY_SEPARATOR;
        foreach ($parts as $i => $part) {
            $parts[$i] = ucfirst($part);
        }
    } else {
        //return;
        $prefix = '';
    }
    //echo 'trying to load ' . $path . ' from ' . $prefix . implode(DIRECTORY_SEPARATOR, $parts) . '.php';
    $fpath = $prefix . implode(DIRECTORY_SEPARATOR, $parts) . '.php';
    if (file_exists($fpath)) {
        return require($fpath);
    } else {
        throw new \Exception('fail to load file "' . $fpath . '" for ' . $path . '');
    }
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
        if (is_string($v)) {
            $s[] = '<p>' . $v . '</p>';
        } else {
            ob_start();
            var_export($v);
            $s[] = '<p>' . ob_get_clean() . '</p>';
        }
    }
    echo implode(PHP_EOL, $s) . PHP_EOL;
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
    return '<pre>' . implode('<hr>', $s) . '</pre>';
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

/**
 * @return array
 */
function collectModels()
{
    $result = array();
    $allClasses = get_declared_classes();
    foreach ($allClasses as $className) {
        $refl = new \ReflectionClass($className);
        if ($refl->isSubclassOf('\\Dja\\Db\\Model\\Model') && !$refl->hasProperty('isProxy')) {
            $result[] = $className;
        }
    }
    return $result;
}

function initModels()
{
    $modelClasses = collectModels();
    foreach ($modelClasses as $modelClass) {
        $modelClass::metadata();
    }
}

/**
 * @param string $dir
 */
function recursiveInclude($dir)
{
    /** @var \SplFileInfo[] $di */
    $di = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir), \RecursiveIteratorIterator::SELF_FIRST);
    $di = new \RegexIterator($di, '/^.+\.php$/i');
    foreach ($di as $file) {
        if ($file->isFile()) {
            //pr($file->getRealPath());
            include $file->getRealPath();
        }
    }
}

/**
 * @return array
 */
function smallPhpInfo()
{
    return array(
        'version' => PHP_VERSION,
        'memory' => formatBytes(memory_get_usage(1), ' '),
        'url' => $_SERVER['REQUEST_URI'],
        'file' => $_SERVER['SCRIPT_FILENAME'],
        'modules' => get_loaded_extensions(),
    );
}

/**
 * @param int $n
 * @param string $sep
 * @return string
 */
function formatBytes($n, $sep = '')
{
    $gbDiv = pow(1024, 3);
    $mbDiv = pow(1024, 2);
    $kbDiv = 1024;
    if ($n > $gbDiv) {
        return round($n / $gbDiv, 3) . $sep . 'Gb';
    }
    if ($n > $mbDiv) {
        return round($n / $mbDiv, 3) . $sep . 'Mb';
    }
    if ($n > $kbDiv) {
        return round($n / $kbDiv, 3) . $sep . 'Kb';
    }
    return $n . $sep . 'B';
}
