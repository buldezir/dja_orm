<?php

namespace Dja\Application;

trait AuthUserTrait
{
    /**
     * @return \App\Models\User
     */
    public function user()
    {
        return $this['auth.user'];
    }
}