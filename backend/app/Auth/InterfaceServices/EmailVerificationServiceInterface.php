<?php

namespace App\Auth\InterfaceServices;

use App\Models\User;

interface EmailVerificationServiceInterface
{
    public function send(User $user): void;

    /** Silently does nothing when the account is missing or already verified. */
    public function resend(string $email): void;

    /** Returns false when the user or hash is invalid. */
    public function verify(int $id, string $hash): bool;
}
