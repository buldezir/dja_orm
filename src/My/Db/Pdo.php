<?php
/**
 * User: Alexander.Arutyunov
 * Date: 09.07.13
 * Time: 13:38
 */
namespace My\Db;

class Pdo extends \PDO
{
    protected $schema;

    public function __construct($dsn, $username = null, $passwd = null, $options = array())
    {
        parent::__construct($dsn, $username, $passwd, $options);
        $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, array('\\My\\Db\\PdoStatement', array($this)));
        $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->schema = Schema::factory($this);
    }

    /**
     * @return Schema
     */
    public function getSchema()
    {
        return $this->schema;
    }

    /**
     * @param string $value
     * @return string
     */
    public function quoteId($value)
    {
        return $this->schema->escapeSchema($value);
    }

    public function quoteAuto($value)
    {
        switch (gettype($value)) {
            case 'integer':
            case 'boolean':
                $type = \PDO::PARAM_INT;
                break;
            default:
                $type = \PDO::PARAM_STR;
                break;
        }
        return $this->quote($value, $type);
    }

    /**
     * @return string
     * @throws \BadMethodCallException
     */
    public function placeHold()
    {
        $args = func_get_args();
        if (count($args) < 2) {
            throw new \BadMethodCallException('Must be string and minimum 1 argument');
        }
        $string = array_shift($args);
        if (substr_count($string, '?') != count($args)) {
            throw new \BadMethodCallException('Number or placeholders didnt match with number of parameters');
        }
        foreach ($args as $value) {
            $string = substr_replace($string, $this->quote($value), strpos($string, '?'), 1);
        }
        return $string;
    }
}