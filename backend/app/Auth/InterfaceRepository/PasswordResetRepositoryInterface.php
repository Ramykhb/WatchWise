<?php

namespace App\Auth\InterfaceRepository;

use App\Models\PasswordReset;

interface PasswordResetRepositoryInterface
{
    /** Deletes any existing reset for the email and stores the new (already hashed) token. */
    public function replaceFor(string $email, string $hashedToken): PasswordReset;

    public function latestFor(string $email): ?PasswordReset;

    public function deleteFor(string $email): void;
}
