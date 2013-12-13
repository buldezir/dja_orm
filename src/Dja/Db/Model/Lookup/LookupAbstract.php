<?php
/**
 * User: Alexander.Arutyunov
 * Date: 11.07.13
 * Time: 14:09
 */
namespace Dja\Db\Model\Lookup;

abstract class LookupAbstract
{
    const TYPE_INT = 'int';
    const TYPE_STRING = 'str';
    const TYPE_BOOL = 'bool';
    const TYPE_NULL = 'null';

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $db;

    protected $operators = array();

    /**
     * @param \Doctrine\DBAL\Connection $conn
     * @return LookupAbstract
     */
    public static function factory($conn)
    {
        $className = '\\Dja\\Db\\Model\\Lookup\\'.ucfirst($conn->getDatabasePlatform()->getName());
        return new $className($conn);
    }

    /**
     * @param $op
     * @return bool
     */
    public function issetLookup($op)
    {
        return isset($this->operators[$op]);
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
        if (!isset($this->operators[$op])) {
            throw new \Exception("unsupported operator '{$op}'");
        }
        switch ($op) {
            case 'exact':
                if ($rawValue === null) {
                    $op = 'isnull';
                }
                break;
            case 'contains':
                $rawValue = '%'.$rawValue.'%';
                break;
            case 'icontains':
                $rawValue = '%'.$rawValue.'%';
                break;
            case 'startswith':
                $rawValue = $rawValue.'%';
                break;
            case 'endswith':
                $rawValue = '%'.$rawValue;
                break;
            case 'istartswith':
                $rawValue = $rawValue.'%';
                break;
            case 'iendswith':
                $rawValue = '%'.$rawValue;
                break;
            case 'iexact':
                $escapedField = 'lower('.$escapedField.')';
                $rawValue = strtolower($rawValue);
                break;
            default:
                break;

        }
        if ($negate) {
            switch ($op) {
                case 'isnotnull':
                    $op = 'isnull';
                    $negate = false;
                    break;
                case 'isnull':
                    $op = 'isnotnull';
                    $negate = false;
                    break;
                default:
                    break;
            }
        }
        if ($negate) {
            $escapedField = 'NOT '.$escapedField;
        }
        $lookup = $this->operators[$op];
        //$lookup = $negate ? 'NOT '.$this->operators[$op] : $this->operators[$op];
        return array($escapedField, $lookup, $rawValue);
    }

    public function __construct($conn)
    {
        $this->db = $conn;
    }

    /**
     * @param int $value
     * @return mixed
     */
    public function toTimeStamp($value)
    {
        return $value;
    }
}