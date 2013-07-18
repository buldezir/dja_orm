<?php
/**
 * User: Alexander.Arutyunov
 * Date: 16.07.13
 * Time: 18:12
 */
namespace My\Auth;

class Acl
{
    const ALL = '*';
    const ANONYMOUS = 0;
    const MANAGER = 2;
    const SUPERVISER = 4;
    const ADMIN_COMPANY = 8;
    const ADMIN_GLOBAL = 16;

    protected static $rules = array();

    protected static $adminGrantAll = false;

    /**
     * @return array
     */
    public static function getRoles()
    {
        return array(
            self::ANONYMOUS => 'Anonymous',
            self::MANAGER => 'Manager',
            self::SUPERVISER => 'Superviser',
            self::ADMIN_COMPANY => 'Company admin',
            self::ADMIN_GLOBAL => 'Cspro admin',
        );
    }

    /**
     * @return array
     */
    public static function getPublicRoles()
    {
        $a = self::getRoles();
        unset($a[self::ANONYMOUS], $a[self::ADMIN_GLOBAL]);
        return $a;
    }

    /**
     * @param $context
     * @param $role
     */
    public static function allow($context, $role)
    {
        if (!isset(self::$rules[$context])) {
            self::$rules[$context] = array();
        }
        if ($role === self::ALL) {
            foreach (self::getRoles() as $role => $s) {
                self::$rules[$context][$role] = $role;
            }

        } else {
            self::$rules[$context][$role] = $role;
        }
    }

    /**
     * @param $context
     * @param $role
     */
    public static function deny($context, $role)
    {
        if ($role === self::ALL) {
            self::$rules[$context] = array();
        } else {
            if (isset(self::$rules[$context])) {
                unset(self::$rules[$context][$role]);
            }
        }
    }

    /**
     * @param $context
     * @param $role
     * @return bool
     */
    public static function isAllowed($context, $role)
    {
        if (self::$adminGrantAll && $role === self::ADMIN_GLOBAL) {
            return true;
        }
        if (!isset(self::$rules[$context])) {
            return false;
        }
        if (!isset(self::$rules[$context][$role])) {
            return false;
        }
        return true;
    }

    /**
     * Allow all for admin role
     */
    public static function adminGodMode()
    {
        self::$adminGrantAll = true;
    }
}