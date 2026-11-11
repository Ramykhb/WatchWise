<?php

namespace App\Auth\InterfaceRepository;

use App\Models\PasswordReset;

interface PasswordResetRepositoryInterface
{
    /**
     * Deletes any existing reset for the email and stores the new (already hashed) token, in one transaction, so two
     * requests at once cannot both leave a row behind.
     */
    public function replaceFor(string $email, string $hashedToken): PasswordReset;

    /** The email's newest reset row, locked until the transaction ends (call inside one). */
    public function latestForUpdate(string $email): ?PasswordReset;

    /** Deletes every reset row of the email; returns how many were deleted. */
    public function deleteFor(string $email): int;
}
