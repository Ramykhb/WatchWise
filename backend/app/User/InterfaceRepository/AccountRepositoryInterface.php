<?php

namespace App\User\InterfaceRepository;

use App\Models\User;
use App\Models\UserPreference;

interface AccountRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateProfile(User $user, array $attributes): void;

    public function findById(int $id): ?User;

    /** Whether some other account already signs in with this address. */
    public function emailBelongsToAnotherUser(string $email, int $userId): bool;

    /**
     * Stores the address the member asked to switch to with a fresh nonce for its links, marked as
     * authorized by the current address straight away or left waiting for it.
     */
    public function storePendingEmail(User $user, string $email, string $nonce, bool $authorized): void;

    /** Records that the current address agreed to the pending change. */
    public function markPendingEmailAuthorized(User $user): void;

    /** Drops the pending address, its nonce and its authorization. */
    public function clearPendingEmail(User $user): void;

    /** Makes the pending address the sign-in email, marks it verified and clears the pending change. */
    public function applyPendingEmail(User $user): void;

    /** The user's preferences row, or an unsaved one when they never set any. */
    public function findOrNewPreferences(User $user): UserPreference;

    public function savePreferences(UserPreference $preferences): void;

    /** Sets the password (hashed by the model cast). */
    public function updatePassword(User $user, string $plainPassword): void;

    public function updateAvatar(User $user, ?string $url): void;

    public function deleteSocialAccounts(User $user, string $provider): void;

    /** Removes the account and everything it owns (foreign keys cascade). */
    public function delete(User $user): void;
}
