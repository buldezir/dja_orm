<?php

namespace App\Controllers;

use Dja\Application\Application;
use Dja\Auth\Acl;
use Dja\Auth\User;
use Dja\Db\Model\Model;
use Symfony\Component\HttpFoundation\Request;

class IndexController
{
    public function indexAction(Request $request, Application $app)
    {
        return $app->render('index.twig');
    }

    public function signinAction(Request $request, Application $app)
    {
        if ($request->isMethod('POST')) {
            try {
                $app['auth.authenticate']($request->request->get('email'), $request->request->get('password'));
                return $app->redirect('/');
            } catch (\Exception $e) {
                return $app->render('sign-in.twig', array('error' => $e->getMessage()));
            }
        }
        return $app->render('sign-in.twig', array('error' => ''));
    }

    public function signupAction(Request $request, Application $app)
    {
        $roles = Acl::getPublicRoles();
        $error = '';
        $errors = array();
        if ($request->isMethod('POST')) {
            //$app['auth.authenticate']($request->request->get('email'), $request->request->get('password'));
            $user = new User();
            try {
                $user->email = $request->request->get('email');
            } catch (\Exception $e) {
                $errors['email'] = array($e->getMessage());
            }

            if ($request->request->get('password') != $request->request->get('password2')) {
                $errors['password2'] = array('Passwords not match');
            }
            if (strlen($request->request->get('password')) >= 8) {
                $user->password = $app['auth._gethash']($request->request->get('password'));
            } else {
                $errors['password'] = array('Password must be 8 chars min');
            }

            $role_id = (int)$request->request->get('role_id');
            if (isset($roles[$role_id])) {
                $user->role_id = $role_id;
            } else {
                $error = 'Naaaahhh -__-';
                $errors['role_id'] = array('Naaaahhh -__-');
            }

            $user->full_name = $request->request->get('full_name');

            if (count($errors) == 0) {
                try {
                    $user->save();
//                    $app->register(new \Silex\Provider\SwiftmailerServiceProvider(), array(
//                        'swiftmailer.transport' => new \Swift_SendmailTransport()
//                    ));
//                    $msg = new \Swift_Message();
//                    $app->mail($msg);

                    $app['session']->getFlashBag()->add('success', "Your workspace successfully created! Don't forget to confirm your email. You'll receive a link shortly");
                    return $app->redirect('/sign-in/');
                    /*
                     * or
                     * $app['auth.remember']($user);
                     * return $app->redirect('/');
                     */
                } catch (\Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }
        return $app->render('sign-up.twig', array('error' => $error, 'errors' => $errors, 'roles' => $roles));
    }

    public function logoutAction(Request $request, Application $app)
    {
        $app['auth.forget']();
        return $app->redirect('/');
    }
}