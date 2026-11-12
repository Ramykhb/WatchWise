<?php

namespace App\Auth\InterfaceRepository;

use App\Models\User;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    /**
     * @param  array{name: string, email: string, password: string|null, avatar?: string|null}  $attributes  password is hashed by the model cast; null for social sign-ups
     */
    public function create(array $attributes): User;

    public function markEmailVerified(User $user): void;

    /**
     * Stamps `email_verified_at` only while it is still null, in one conditional `UPDATE`; true when this call
     * verified the address. Two verification clicks at once therefore verify (and fire `Verified`) once.
     */
    public function markEmailVerifiedIfUnverified(User $user): bool;

    /** Sets the password (hashed by the model cast). */
    public function updatePassword(User $user, string $plainPassword): void;

    /**
     * Stores a fresh hash of the same password (after a login, when the hashing cost changed), bypassing model
     * events: it is not a new password, so the `saving` hook that drops a pending account deletion does not run.
     * Compare-and-set: only while the stored hash is still `$verifiedHash` (the one the password was just checked
     * against), so a password reset or change committed in between is never reverted. True when it was written.
     */
    public function rehashPassword(User $user, string $verifiedHash, string $plainPassword): bool;

    /** Removes the password (and its "set without proof" mark): the account then signs in only through its social login or a reset. */
    public function clearPassword(User $user): void;

    /** Removes the user's profile photo, so their initials are shown instead. */
    public function clearAvatar(User $user): void;

    /** Stamps `last_active_at` with the current time without touching `updated_at`. */
    public function markActive(User $user): void;
}
