<?php

namespace Dja\Db\Model\Query;

/**
 * synonym to Doctrine\DBAL\Query\Expression\CompositeExpression
 *
 * Class QueryPart
 * @package Dja\Db\Model\Query
 */
class QueryPart
{
    const TYPE_AND = 'AND';
    const TYPE_OR = 'OR';

    protected $type = self::TYPE_AND;
    protected $arguments = [];

    public static function _OR(array $arguments)
    {
        return new static(self::TYPE_OR, $arguments);
    }

    public static function _AND(array $arguments)
    {
        return new static(self::TYPE_AND, $arguments);
    }

    /**
     * @param $type
     * @param array $arguments
     */
    public function __construct($type, array $arguments)
    {
        $this->setType($type);
        $this->setArguments($arguments);
    }

    /**
     * @param array $arguments
     * @return $this
     */
    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;
        return $this;
    }

    /**
     * @param $argument
     * @return $this
     */
    public function addArgument($argument)
    {
        $this->arguments[] = $argument;
        return $this;
    }

    /**
     * @return array
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @param $type
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setType($type)
    {
        $check = [
            self::TYPE_OR,
            self::TYPE_AND,
        ];
        if (!in_array($type, $check)) {
            throw new \InvalidArgumentException("Unidentified type '$type'");
        }
        $this->type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }


}