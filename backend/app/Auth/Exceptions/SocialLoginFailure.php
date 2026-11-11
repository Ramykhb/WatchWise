<?php

namespace App\Auth\Exceptions;

use Throwable;

/**
 * An expected way for a social sign-in to end without signing in. The callback is a browser redirect, so these are
 * never rendered as JSON: `SocialAuthController` sends the browser to the frontend failure page with `reason()`.
 */
interface SocialLoginFailure extends Throwable
{
    /** The `reason` query value of FRONTEND_URL/auth/callback?status=error. */
    public function reason(): string;
}
