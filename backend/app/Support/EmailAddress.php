<?php

namespace App\Support;

use Illuminate\Support\Str;

final class EmailAddress
{
    /**
     * Emails are stored and compared lowercase and trimmed, so "Ali@Example.com " and "ali@example.com"
     * are one sign-in address everywhere (sign-up, sign-in, password reset, email change, social sign-in).
     */
    public static function normalize(string $email): string
    {
        return Str::lower(trim($email));
    }
}
