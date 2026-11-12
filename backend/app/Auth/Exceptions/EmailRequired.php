<?php

namespace App\Auth\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/** The provider gave no email, and no account is linked to this provider identity yet. */
class EmailRequired extends RuntimeException implements ShouldntReport, SocialLoginFailure
{
    public function reason(): string
    {
        return 'email_required';
    }
}
