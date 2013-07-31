<?php
/**
 * User: Alexander.Arutyunov
 * Date: 26.07.13
 * Time: 16:46
 */

namespace Dja\Application;

use Dja\Auth\Acl;

class SecureRoute extends \Silex\Route
{
    /**
     * @var \Closure
     */
    protected static $reverseControllerCallback;

    protected $isSecured = false;

    protected function secure($c)
    {
        if (!$this->isSecured) {
            $this->before(function ($request, Application $app) use ($c) {
                if (!$app->user()->isAllowed($c)) {
                    throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException();
                }
            });
            $this->isSecured = true;
        }
    }

    public function allow()
    {
        $fn = self::$reverseControllerCallback;
        $c = $fn($this->getDefault('_controller'));
        foreach (func_get_args() as $role) {
            Acl::allow($c, $role);
        }
        $this->secure($c);
        return $this;
    }

    public function deny()
    {
        $fn = self::$reverseControllerCallback;
        $c = $fn($this->getDefault('_controller'));
        foreach (func_get_args() as $role) {
            Acl::deny($c, $role);
        }
        $this->secure($c);
        return $this;
    }

    public static function setReverseControllerCallback(\Closure $fn)
    {
        self::$reverseControllerCallback = $fn;
    }
}