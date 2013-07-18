<?php
/**
 * User: Alexander.Arutyunov
 * Date: 11.07.13
 * Time: 14:09
 */
namespace My\Db;

abstract class Schema
{
    const TYPE_INT = 'int';
    const TYPE_STRING = 'str';
    const TYPE_BOOL = 'bool';
    const TYPE_NULL = 'null';

    /**
     * @var Pdo
     */
    protected $db;

    protected $operators = array();

    /**
     * @param \PDO|string $conn
     * @return Schema
     */
    public static function factory($conn)
    {
        if ($conn instanceof \PDO) {
            $className = '\\My\\Db\\SchemaAdapter\\'.ucfirst($conn->getAttribute(\PDO::ATTR_DRIVER_NAME));
            return new $className($conn);
        } else {
            $className = 'SchemaAdapter\\'.ucfirst($conn);
            return new $className;
        }
    }

    /**
     * @param $op
     * @param $escapedField
     * @param $rawValue
     * @return array
     * @throws \Exception
     */
    public function getLookup($op, $escapedField, $rawValue)
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
            case 'icontains':
            case 'startswith':
            case 'endswith':
            case 'istartswith':
            case 'iendswith':
                $rawValue = '%'.$rawValue.'%';
                break;
            case 'iexact':
                $escapedField = 'lower('.$escapedField.')';
                $rawValue = strtolower($rawValue);
                break;
            default:
                break;

        }
        return array($escapedField, $this->operators[$op], $rawValue);
    }

    public function __construct($conn = null)
    {
        if ($conn) {
            $this->db = $conn;
        } else {
            $this->db = \Application::getInstance()->db();
        }
    }

    /**
     * @return array
     */
    abstract public function getTables();

    /**
     * @param string $table
     * @return array
     */
    abstract public function getColumns($table);

    /**
     * @param $value
     * @return mixed
     */
    abstract public function escapeSchema($value);

    /**
     * @param $value
     * @return mixed
     */
    abstract public function escapeValue($value);

    /**
     * @param int $value
     * @return mixed
     */
    public function toTimeStamp($value)
    {
        return $value;
    }
}