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
     * @var \Doctrine\DBAL\Connection
     */
    protected $db;

    /**
     * @param \Doctrine\DBAL\Connection $conn
     * @return LookupAbstract
     */
    public static function factory($conn)
    {
        $className = '\\Dja\\Db\\Model\\Lookup\\' . ucfirst($conn->getDatabasePlatform()->getName());
        return new $className($conn);
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
        $rawValue = '%' . $rawValue . '%';
        return 'LIKE BINARY %s';
    }

    public function lookupIContains(&$escapedField, &$rawValue, &$negate)
    {
        $rawValue = '%' . $rawValue . '%';
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
        if (!method_exists($this, $method)) {
            throw new \Exception("unsupported operator '{$op}'");
        }
        $lookupQ = $this->$method($escapedField, $rawValue, $negate);
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
        return method_exists($this, $this->getLookUpMethod($op));
    }

    /**
     * @param \Doctrine\DBAL\Connection $conn
     */
    public function __construct(\Doctrine\DBAL\Connection $conn)
    {
        $this->db = $conn;
    }
}