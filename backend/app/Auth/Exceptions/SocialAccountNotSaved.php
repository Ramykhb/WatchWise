<?php

namespace App\Auth\Exceptions;

use Illuminate\Database\QueryException;
use RuntimeException;

/**
 * The social sign-in could not write the account. Reported (the browser gets `provider_error`), so it carries no
 * SQL, bindings or previous exception: a `QueryException` message would put the user's email and name in the log.
 */
class SocialAccountNotSaved extends RuntimeException
{
    public static function afterConflicts(int $attempts): self
    {
        return new self("Social sign-in still hit a unique constraint after {$attempts} attempts.");
    }

    public static function fromQuery(QueryException $e): self
    {
        $sqlState = is_array($e->errorInfo) && is_string($e->errorInfo[0] ?? null) ? $e->errorInfo[0] : 'unknown';

        return new self("Social sign-in could not save the account (SQLSTATE {$sqlState}).");
    }
}
