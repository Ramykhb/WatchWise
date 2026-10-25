<?php

namespace App\User\InterfaceRepository;

use App\Models\User;
use App\Models\UserPreference;

interface AccountRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  bool  $unverifyEmail  a new address has to be confirmed again
     */
    public function updateProfile(User $user, array $attributes, bool $unverifyEmail): void;

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
