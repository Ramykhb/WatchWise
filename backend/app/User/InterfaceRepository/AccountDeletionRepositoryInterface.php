<?php

namespace App\User\InterfaceRepository;

use App\Models\User;

/** The pending account deletion an account confirms from its inbox (`deletion_requested_at`, `deletion_nonce`). */
interface AccountDeletionRepositoryInterface
{
    /** Stores a new pending deletion with a fresh nonce for its links, replacing any earlier one. */
    public function storeDeletionRequest(User $user, string $nonce): void;

    /**
     * Drops the pending deletion. With a nonce, only in one conditional update while the row still holds
     * that exact request, the same email and the same password, so it also serves as the compare-and-set
     * that consumes a confirmation link once. Returns whether a row was updated.
     */
    public function clearDeletionRequest(User $user, ?string $nonce = null): bool;
}
