<?php

namespace Dja\Auth;

class AnonymousUser implements AuthUser
{
    public $user_id = null;
    public $role_id = Acl::ANONYMOUS;

    public function __toString()
    {
        return 'AnonymousUser';
    }

    public function isAuthenticated()
    {
        return false;
    }

    public function isAnonymous()
    {
        return true;
    }

    public function isAllowed($context)
    {
        return Acl::isAllowed($context, $this->role_id);
    }
}