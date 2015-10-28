<?php

namespace Dja\Db\Model\Lookup;

use Dja\Db\Model\Query\BaseQuerySet;
use Dja\Util\Inflector;

/**
 * Class LookupAbstract
 * @package Dja\Db\Model\Lookup
 */
abstract class LookupAbstract
{
    /**
     * @var array
     */
    protected static $instances = [];

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $db;

    /**
     * @var \Closure[]
     */
    protected $customLookups = [];

    /**
     * @param \Doctrine\DBAL\Connection $conn
     * @return LookupAbstract
     */
    public static function getInstance(\Doctrine\DBAL\Connection $conn)
    {
        $className = '\\Dja\\Db\\Model\\Lookup\\' . ucfirst($conn->getDatabasePlatform()->getName());
        if (!isset(self::$instances[$className])) {
            self::$instances[$className] = new $className($conn);
        }
        return self::$instances[$className];
    }

    /**
     * $lkpr = \Dja\Db\Model\Lookup\LookupAbstract::getInstance($dbConn);
     * $lkpr->add('mylookup', function (&$escapedField, $rawValue, $negate, $lookuper) {
     *     $escapedField = '';
     *     return 'user_id < '.$lookuper->getDb()->quote($rawValue).' AND lastname IS NOT NULL';
     * });
     * $qs = ModelUser::objects()->filter(['pk__mylookup' => 10]);
     *
     * @param $name
     * @param callable $fn
     * @throws \InvalidArgumentException
     */
    public function add($name, \Closure $fn)
    {
        if (array_key_exists($name, $this->customLookups)) {
            throw new \InvalidArgumentException("Custom lookup with name '{$name}' already exist");
        }
        $this->customLookups[$name] = $fn;
    }

    abstract public function lookupYear(&$escapedField, &$rawValue, &$negate);

    abstract public function lookupMonth(&$escapedField, &$rawValue, &$negate);

    abstract public function lookupDay(&$escapedField, &$rawValue, &$negate);

    abstract public function lookupWeekDay(&$escapedField, &$rawValue, &$negate);

    abstract public function lookupHour(&$escapedField, &$rawValue, &$negate);

    abstract public function lookupMinute(&$escapedField, &$rawValue, &$negate);

    abstract public function lookupSecond(&$escapedField, &$rawValue, &$negate);

    public function lookupExact(&$escapedField, &$rawValue, &$negate)
    {
        if ($rawValue === null) {
            return $this->lookupIsNull($escapedField, $rawValue, $negate);
        }
        return '= %s';
    }

    public function lookupIsNull(&$escapedField, &$rawValue, &$negate)
    {
        if ($negate) {
            $negate = false;
            return $this->lookupIsNotNull($escapedField, $rawValue, $negate);
        }
        return 'IS NULL';
    }

    public function lookupIsNotNull(&$escapedField, &$rawValue, &$negate)
    {
        if ($negate) {
            $negate = false;
            return $this->lookupIsNull($escapedField, $rawValue, $negate);
        }
        return 'IS NOT NULL';
    }

    public function lookupContains(&$escapedField, &$rawValue, &$negate)
    {
        $rawValue = '%' . str_replace('%', '\\%', $rawValue) . '%';
        return 'LIKE BINARY %s';
    }

    public function lookupIContains(&$escapedField, &$rawValue, &$negate)
    {
        $rawValue = '%' . str_replace('%', '\\%', $rawValue) . '%';
        return 'LIKE %s';
    }

    public function lookupStartsWith(&$escapedField, &$rawValue, &$negate)
    {
        $rawValue = $rawValue . '%';
        $mock = '';
        return $this->lookupContains($escapedField, $mock, $negate);
    }

    public function lookupIStartsWith(&$escapedField, &$rawValue, &$negate)
    {
        $rawValue = $rawValue . '%';
        $mock = '';
        return $this->lookupIContains($escapedField, $mock, $negate);
    }

    public function lookupEndsWith(&$escapedField, &$rawValue, &$negate)
    {
        $rawValue = '%' . $rawValue;
        $mock = '';
        return $this->lookupContains($escapedField, $mock, $negate);
    }

    public function lookupIEndsWith(&$escapedField, &$rawValue, &$negate)
    {
        $rawValue = '%' . $rawValue;
        $mock = '';
        return $this->lookupIContains($escapedField, $mock, $negate);
    }

    public function lookupRegex(&$escapedField, &$rawValue, &$negate)
    {
        return 'REGEXP BINARY %s';
    }

    public function lookupIRegex(&$escapedField, &$rawValue, &$negate)
    {
        return 'REGEXP %s';
    }

    public function lookupGt(&$escapedField, &$rawValue, &$negate)
    {
        return '> %s';
    }

    public function lookupGte(&$escapedField, &$rawValue, &$negate)
    {
        return '>= %s';
    }

    public function lookupLt(&$escapedField, &$rawValue, &$negate)
    {
        return '< %s';
    }

    public function lookupLte(&$escapedField, &$rawValue, &$negate)
    {
        return '<= %s';
    }

    public function lookupRaw(&$escapedField, &$rawValue, &$negate)
    {
        $escapedField = '';
        return '%s';
    }

    public function lookupIn(&$escapedField, &$rawValue, &$negate)
    {
        if (!is_array($rawValue) && !$rawValue instanceof \Dja\Db\Model\Expr && !$rawValue instanceof BaseQuerySet) {
            throw new \InvalidArgumentException("value for IN lookup must me array or QuerySet or Expr");
        }
        if (is_array($rawValue)) {
            $rawValue = array_map([$this->db, 'quote'], $rawValue);
            $rawValue = Expr(implode(', ', $rawValue));
        }
        return 'IN (%s)';
    }

    public function lookupRange(&$escapedField, &$rawValue, &$negate)
    {
        if (!is_array($rawValue) || count($rawValue) !== 2) {
            throw new \InvalidArgumentException("value for RANGE lookup must me array with two elements");
        }
        $rawValue = Expr($this->db->quote($rawValue[0]) . ' AND ' . $this->db->quote($rawValue[1]));
        return 'BETWEEN %s';
    }

    /**
     * @param string $op lookup operator from $this->operators
     * @param string $escapedField
     * @param $rawValue
     * @param bool $negate
     * @throws \Exception
     * @return array
     */
    public function getLookup($op, $escapedField, $rawValue, $negate = false)
    {
        $method = $this->getLookUpMethod($op);
        if (method_exists($this, $method)) {
            $lookupQ = $this->$method($escapedField, $rawValue, $negate);
        } elseif (array_key_exists($op, $this->customLookups)) {
            $fn = $this->customLookups[$op];
            $lookupQ = $fn($escapedField, $rawValue, $negate, $this);
        } else {
            throw new \Exception("unsupported operator '{$op}'");
        }
        if ($negate) {
            $escapedField = 'NOT ' . $escapedField;
        }
        return [$escapedField, $lookupQ, $rawValue];
    }

    /**
     * @param $op
     * @return string
     */
    public function getLookUpMethod($op)
    {
        return Inflector::camelize('lookup_' . $op);
    }

    /**
     * @param $op
     * @return bool
     */
    public function issetLookup($op)
    {
        return method_exists($this, $this->getLookUpMethod($op)) || array_key_exists($op, $this->customLookups);
    }

    /**
     * @param \Doctrine\DBAL\Connection $conn
     */
    public function __construct(\Doctrine\DBAL\Connection $conn)
    {
        $this->db = $conn;
    }

    /**
     * @return \Doctrine\DBAL\Connection
     */
    public function getDb()
    {
        return $this->db;
    }
}