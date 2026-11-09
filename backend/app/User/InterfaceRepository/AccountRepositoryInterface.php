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

    /** Stores (or with null, clears) the address the member asked to switch to. */
    public function updatePendingEmail(User $user, ?string $email): void;

    /** Makes the pending address the sign-in email, marks it verified and clears the pending one. */
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
