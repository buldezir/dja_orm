<?php
/**
 * User: Alexander.Arutyunov
 * Date: 27.07.13
 * Time: 15:35
 */

/**
 * @param $start
 * @param $limit
 * @param int $step
 * @return Generator
 * @throws \LogicException
 */
function xrange($start, $limit, $step = 1)
{
    if ($start < $limit) {
        if ($step <= 0) {
            throw new \LogicException('Step must be > 0');
        }
        for ($i = $start; $i <= $limit; $i += $step) {
            yield $i;
        }
    } else {
        if ($step >= 0) {
            throw new \LogicException('Step must be < 0');
        }
        for ($i = $start; $i >= $limit; $i += $step) {
            yield $i;
        }
    }
}

/**
 * @param \Dja\Db\Model\Query\BaseQuerySet $qs
 * @param int $chunkSize
 * @return \Iterator
 */
function chunkedIterator(\Dja\Db\Model\Query\BaseQuerySet $qs, $chunkSize = 1000)
{
    $qs = clone $qs;
    $baseLimit = $qs->_qb()->getMaxResults();
    $allCount = $qs->doCount();
    $expectingResultsCount = $baseLimit !== null ? min($baseLimit, $allCount) : $allCount;
    $numIters = ceil($expectingResultsCount / $chunkSize);
    foreach (xrange(0, $numIters - 1) as $offsetMultiplier) {
        $offset = $offsetMultiplier * $chunkSize;
        $qs->_qb()->setFirstResult($offset)->setMaxResults($chunkSize);
        foreach ($qs as $row) {
            yield $row;
        }
    }
}

/**
 * @param $value
 * @param null $default
 * @return null
 */
function ifsetor(&$value, $default = null)
{
    return isset($value) ? $value : $default;
}

/**
 * @param $tpl
 * @param $arguments
 * @return string
 */
function fs($tpl, $arguments)
{
    if (func_num_args() === 2 && is_array($arguments)) { // named args
        $args = $arguments;
        foreach ($args as $key => $value) {
            if (is_int($key)) {
                $tpl = substr_replace($tpl, $value, strpos($tpl, '%s'), 2);
            } else {
                $tpl = str_replace("%({$key})", $value, $tpl);
            }
        }
        return $tpl;
    } else {
        $args = func_get_args();
        $tpl = array_shift($args);
        return vsprintf($tpl, $args);
    }
}

/**
 * @param array $array1
 * @param array $array2
 * @return array
 */
function array_diff_assoc_recursive($array1, $array2)
{
    $difference = [];
    foreach ($array1 as $key => $value) {
        if (is_array($value)) {
            if (!isset($array2[$key]) || !is_array($array2[$key])) {
                $difference[$key] = $value;
            } else {
                $new_diff = array_diff_assoc_recursive($value, $array2[$key]);
                if (!empty($new_diff))
                    $difference[$key] = $new_diff;
            }
        } else if (!array_key_exists($key, $array2) || $array2[$key] !== $value) {
            $difference[$key] = $value;
        }
    }
    return $difference;
}

/**
 * http://stackoverflow.com/questions/14445582/change-ipv4-to-ipv6-string
 * @param $addr_str
 * @return bool|string
 */
function expand_ip_address($addr_str)
{
    /* First convert to binary, which also does syntax checking */
    $addr_bin = @inet_pton($addr_str);
    if ($addr_bin === FALSE) {
        return FALSE;
    }
    $addr_hex = bin2hex($addr_bin);
    /* See if this is an IPv4-Compatible IPv6 address (deprecated) or an
       IPv4-Mapped IPv6 Address (used when IPv4 connections are mapped to
       an IPv6 sockets and convert it to a normal IPv4 address */
    if (strlen($addr_bin) === 16 && substr($addr_hex, 0, 20) === str_repeat('0', 20)) {
        /* First 80 bits are zero: now see if bits 81-96 are either all 0 or all 1 */
        if (substr($addr_hex, 20, 4) === '0000' || substr($addr_hex, 20, 4) === 'ffff') {
            /* Remove leading bits so only the IPv4 bits remain */
            $addr_bin = substr($addr_hex, 12);
        }
    }
    /* Then differentiate between IPv4 and IPv6 */
    if (strlen($addr_bin) === 4) {
        /* IPv4: print each byte as 3 digits and add dots between them */
        $ipv4_bytes = str_split($addr_bin);
        $ipv4_ints = array_map('ord', $ipv4_bytes);
        return vsprintf('%03d.%03d.%03d.%03d', $ipv4_ints);
    } else {
        /* IPv6: print as hex and add colons between each group of 4 hex digits */
        return implode(':', str_split($addr_hex, 4));
    }
}

/**
 * easy slug making func
 * http://www.php.net/manual/en/transliterator.transliterate.php
 * @param $string
 * @return string
 */
function slugify($string)
{
    $string = transliterator_transliterate("Any-Latin; NFD; [:Nonspacing Mark:] Remove; NFC; [:Punctuation:] Remove; Lower();", $string);
    $string = preg_replace('/[-\s]+/', '-', $string);
    return trim($string, '-');
}

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
 * @param array $array
 * @return string
 */
function dumpArray(array $array)
{
    $a = [];
    foreach ($array as $k => $v) {
        $v = var_export($v, 1);
        if (!is_int($k)) {
            $a[] = "'$k' => $v";
        } else {
            $a[] = $v;
        }
    }
    return implode(', ', $a);
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
            $s[] = '<pre>' . $v . '</pre>';
        } else {
            ob_start();
            var_export($v);
            $s[] = '<pre>' . ob_get_clean() . '</pre>';
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

/**
 * @param int $limit
 * @return int[]
 */
//function xrange($limit)
//{
//    for ($i = 0; $i <= $limit; $i++) {
//        yield $i;
//    }
//}