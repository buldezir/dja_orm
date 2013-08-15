<?php
/**
 * User: Alexander.Arutyunov
 * Date: 26.07.13
 * Time: 15:20
 */

namespace Dja\Application;

/**
 * Class Application
 * @package Dja\Application
 */
class Application extends \Silex\Application
{
    protected static $_inst;

    /**
     * @return Application
     */
    public static function getInstance()
    {
        if (!self::$_inst) {
            self::$_inst = new self(array('debug' => true));
        }
        return self::$_inst;
    }

    /**
     * @param array $values
     * @throws \Exception
     */
    public function __construct(array $values = array())
    {
        if (!defined('DJA_PATH')) {
            throw new \Exception('Please define Dja library path "DJA_PATH"');
        }
        if (!defined('DJA_APP_PATH')) {
            throw new \Exception('Please define Dja application path "DJA_APP_PATH"');
        }
        if (!isset($values['route_class'])) {
            $values['route_class'] = 'Dja\\Application\\SecureRoute';
        }
        parent::__construct($values);

        $this->before(function ($request) {
            recursiveInclude(DJA_APP_PATH.'/models');
            initModels();
            pr('mem usage: <b>' . (memory_get_usage(1) / 1024 / 1024) . 'mb</b> | mem peak usage: <b>' . (memory_get_peak_usage(1) / 1024 / 1024) . 'mb</b>');
        });
        $this->after(function ($request, $response) {
            pr('mem usage: <b>' . (memory_get_usage(1) / 1024 / 1024) . 'mb</b> | mem peak usage: <b>' . (memory_get_peak_usage(1) / 1024 / 1024) . 'mb</b>');
        });
    }

    /**
     * @return \Dja\Db\Pdo
     */
    public function db()
    {
        return $this['pdo_db'];
    }

    /**
     * @return \Dja\Auth\AuthUser
     */
    public function user()
    {
        return $this['auth.user'];
    }
}
