<?php

namespace Dja\Application;

trait DbTrait
{
    /**
     * @return \PDO
     */
    public function db()
    {
        return $this['pdo_db'];
    }
}