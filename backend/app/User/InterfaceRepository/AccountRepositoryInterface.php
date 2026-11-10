<?php

namespace App\User\InterfaceRepository;

use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Database\UniqueConstraintViolationException;

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

    /**
     * Records that the current address agreed to the pending change, in one conditional update: only
     * while the row still holds this pending address, this nonce and the same password, and is not
     * authorized yet. Returns false when nothing matched.
     */
    public function authorizePendingEmail(User $user, string $nonce): bool;

    /**
     * Drops the pending address, its nonce and its authorization. With a nonce, only if the row still
     * holds that exact request (and the same password). Returns whether a row was updated.
     */
    public function clearPendingEmail(User $user, ?string $nonce = null): bool;

    /**
     * Makes the pending address the sign-in email, marks it verified, and clears the pending change and
     * the "password set without proof" mark, in one conditional update: only while the row still holds
     * this exact authorized request and the same password. Returns false when nothing matched.
     *
     * @throws UniqueConstraintViolationException when another account took the address meanwhile
     */
    public function applyPendingEmail(User $user, string $nonce): bool;

    /** The user's preferences row, or an unsaved one when they never set any. */
    public function findOrNewPreferences(User $user): UserPreference;

    public function savePreferences(UserPreference $preferences): void;

    /** Sets the password (hashed by the model cast). */
    public function updatePassword(User $user, string $plainPassword): void;

    /**
     * Sets a first password on an account that has none and stamps `password_set_without_proof_at`, checked
     * and written under a lock on the user's row. Returns false (changing nothing) when the row already holds
     * a password, e.g. one set by a concurrent request.
     */
    public function setFirstPassword(User $user, string $plainPassword): bool;

    /** The owner proved control of the inbox, so a password chosen without proof now counts as proof. */
    public function clearPasswordSetWithoutProof(User $user): void;

    /** `$avatar` is a path on the public disk for an uploaded picture, or null to remove it. */
    public function updateAvatar(User $user, ?string $avatar): void;

    public function deleteSocialAccounts(User $user, string $provider): void;

    /**
     * Deletes the account's rows for this provider unless they are its only linked sign-in, checked and
     * written under a lock on the user's row so two disconnects at once cannot remove both. Returns false
     * (deleting nothing) when no other provider would remain; true otherwise, also when the provider was
     * not linked at all.
     */
    public function deleteSocialAccountsKeepingAnother(User $user, string $provider): bool;

    /** Removes the account and everything it owns (foreign keys cascade). */
    public function delete(User $user): void;
}
