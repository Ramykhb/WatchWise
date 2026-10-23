<?php

namespace App\Auth\InterfaceServices;

use App\Auth\DTOs\Requests\ResendVerificationData;
use App\Auth\DTOs\Requests\VerifyEmailData;
use App\Models\User;

interface EmailVerificationServiceInterface
{
    public function send(User $user): void;

    /** Silently does nothing when the account is missing or already verified. */
    public function resend(ResendVerificationData $data): void;

    /** Returns false when the user or hash is invalid. */
    public function verify(VerifyEmailData $data): bool;
}
