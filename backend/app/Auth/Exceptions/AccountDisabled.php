<?php

namespace App\Auth\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/** The account a social sign-in resolved to is disabled. Nothing was changed. */
class AccountDisabled extends RuntimeException implements ShouldntReport, SocialLoginFailure
{
    public function reason(): string
    {
        return 'account_disabled';
    }
}
