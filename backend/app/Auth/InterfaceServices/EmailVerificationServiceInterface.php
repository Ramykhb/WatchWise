<?php

namespace App\Auth\InterfaceServices;

use App\Auth\DTOs\Requests\ResendVerificationData;
use App\Auth\DTOs\Requests\VerifyEmailData;
use App\Auth\DTOs\Responses\VerifyEmailOutcome;
use App\Models\User;

interface EmailVerificationServiceInterface
{
    /** Mails the signed verification link after the response (retried, never failing the caller). */
    public function send(User $user): void;

    /** Silently does nothing when the account is missing or already verified. */
    public function resend(ResendVerificationData $data): void;

    /**
     * Verifies the address of the link's account. Idempotent: a second click (or two at once) answers
     * `already-verified` and changes nothing. `invalid` when the account is gone or its address changed.
     */
    public function verify(VerifyEmailData $data): VerifyEmailOutcome;
}
