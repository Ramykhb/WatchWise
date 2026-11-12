<?php

namespace App\Auth\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/** The user declined or cancelled the provider's consent screen (`error=access_denied` on the callback). */
class SocialLoginCancelled extends RuntimeException implements ShouldntReport, SocialLoginFailure
{
    public function reason(): string
    {
        return 'access_denied';
    }
}
