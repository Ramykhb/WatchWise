<?php

namespace App\Auth\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/** The provider has not verified the email, so no account is linked to or created from it. */
class UnverifiedProviderEmail extends RuntimeException implements ShouldntReport, SocialLoginFailure
{
    public function reason(): string
    {
        return 'email_unverified';
    }
}
