<?php

namespace My\Provider;

use Silex\Application;
use Silex\ServiceProviderInterface;

use My\Db\Pdo;

class PdoDbProvider implements ServiceProviderInterface
{
    /**
     * Registers services on the given app.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Application $app An Application instance
     */
    public function register(Application $app)
    {
        $app['pdo_db'] = $app->share(function ($app) {
            $db = new Pdo("pgsql:dbname={$app['pdo_db.db_name']};host={$app['pdo_db.host']}", $app['pdo_db.username'], $app['pdo_db.password']);
            //$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return $db;
        });
    }

    /**
     * Bootstraps the application.
     *
     * This method is called after all services are registers
     * and should be used for "dynamic" configuration (whenever
     * a service must be requested).
     */
    public function boot(Application $app)
    {
        // TODO: Implement boot() method.
    }


}