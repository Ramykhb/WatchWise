<?php

namespace App\Auth\DTOs\Responses;

/**
 * What the confirming POST of a verification link did: `success` (verified now), `already-verified` (the address
 * was verified before, by this link or another way) or `invalid` (no such account, or the address changed since the
 * link was mailed). A bad or expired signature never reaches the service: `bootstrap/app.php` answers it with
 * `invalid` (403) or `expired` (410).
 */
enum VerifyEmailOutcome: string
{
    case Success = 'success';
    case AlreadyVerified = 'already-verified';
    case Invalid = 'invalid';
}
