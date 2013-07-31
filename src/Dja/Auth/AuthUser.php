<?php

namespace Dja\Auth;

interface AuthUser
{
    /**
     * @return string
     */
    public function __toString();

    /**
     * @return bool
     */
    public function isAuthenticated();

    /**
     * @return bool
     */
    public function isAnonymous();

    /**
     * @param string $context
     * @return bool
     */
    public function isAllowed($context);
}