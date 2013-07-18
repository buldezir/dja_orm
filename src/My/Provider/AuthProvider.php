<?php

namespace My\Provider;

use Silex\Application;
use Silex\ServiceProviderInterface;

use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class AuthProvider implements ServiceProviderInterface
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
//        $app['auth'] = $app->share(function ($app) {
//
//        });
        $app['auth.auth_user_class'] = '\\App\\Models\\User';
        $app['auth.anonym_user_class'] = '\\My\\Auth\\AnonymousUser';

        $app['auth.is_authed'] = $app->share(function () use ($app) {
//            return false;
            $session = $app['session'];
            if ($session && $session->has('auth.is_authed')) {
                return (bool) $session->get('auth.is_authed');
            } else {
                return false;
            }
        });

        $app['auth._gethash'] = $app->protect(function ($v) {
            return sha1($v);
        });

        $app['auth._getuser'] = $app->protect(function () use ($app) {
            if ($app['auth.is_authed']) {
                $session = $app['session'];
                $uid = $session->get('auth.id');
                $class = $app['auth.auth_user_class'];
                return $class::objects()->get($uid);
            } else {
                $class = $app['auth.anonym_user_class'];
                return new $class;
            }
        });

        $app['auth.user'] = $app->share(function () use ($app) {
            return $app['auth._getuser']();
        });

        $app['auth.remember'] = $app->protect(function (\My\Auth\AuthUser $user) use ($app) {
            if ($user->isAuthenticated() && !$user->isNewRecord()) {
                $session = $app['session'];
                $session->set('auth.is_authed', true);
                $session->set('auth.id', $user->user_id);

                $app['auth.is_authed'] = true;
                $app['auth.user'] = $user;
            } else {
                throw new \InvalidArgumentException('Must be authenticated real user to remember');
            }
        });

        $app['auth.forget'] = $app->protect(function () use ($app) {
            if ($app['auth.is_authed']) {
                $session = $app['session'];
                $session->set('auth.is_authed', false);
                $session->remove('auth.id');
                $app['auth.is_authed'] = false;
                $app['auth.user'] = $app['auth._getuser']();
            }
        });

        $app['auth.authenticate'] = $app->protect(function ($id, $pwd, $remember = true) use ($app) {
            $class = $app['auth.auth_user_class'];
            $user = $class::objects()->filter(['email' => $id])->current();
            if ($user) {
                if (!$user->is_active) {
                    throw new DisabledException('Account is disabled');
                }
                if ($user->password == $app['auth._gethash']($pwd)) {
                    $app['auth.remember']($user);
                } else {
                    throw new BadCredentialsException('Invalid password');
                }
            } else {
                throw new UsernameNotFoundException('Can\'t find user with such identity');
            }
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

    }
}