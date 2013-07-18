<?php

namespace My\Auth;

interface AuthUser
{
    public function __toString();
    public function isAuthenticated();
    public function isAnonymous();
    public function isAllowed($context);
}