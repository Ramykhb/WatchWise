<?php

namespace App\Auth\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/** The callback's `state` is missing, does not match the `oauth_state` cookie, or was issued for another provider. */
class InvalidOAuthState extends RuntimeException implements ShouldntReport, SocialLoginFailure
{
    public function reason(): string
    {
        return 'invalid_state';
    }
}
