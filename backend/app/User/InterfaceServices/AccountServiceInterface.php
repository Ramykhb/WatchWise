<?php

namespace App\User\InterfaceServices;

use App\Auth\DTOs\Responses\AuthResponse;
use App\Models\User;
use App\User\DTOs\Requests\ChangePasswordData;
use App\User\DTOs\Requests\SetPasswordData;
use App\User\DTOs\Requests\UpdatePreferencesData;
use App\User\DTOs\Requests\UpdateProfileData;
use App\User\DTOs\Responses\AccountResponse;
use App\User\Exceptions\CannotDisconnectLastLogin;
use App\User\Exceptions\IncorrectPassword;
use App\User\Exceptions\NoPasswordSet;
use App\User\Exceptions\PasswordAlreadySet;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

interface AccountServiceInterface
{
    public function show(User $user): AccountResponse;

    /** Name, handle, location, bio and visibility; the email changes through EmailChangeServiceInterface. */
    public function updateProfile(User $user, UpdateProfileData $data): AccountResponse;

    /** Merges the switches that were sent into the saved settings; unknown keys are ignored. */
    public function updatePreferences(User $user, UpdatePreferencesData $data): AccountResponse;

    /**
     * Changes an existing password after checking the current one. Revokes every refresh token the user
     * holds and starts a fresh session for the calling device, so only the other devices are signed out.
     * Returns the new session for the controller's cookies.
     *
     * @throws NoPasswordSet when the account has no password yet (it sets one with setPassword())
     * @throws IncorrectPassword
     */
    public function changePassword(User $user, ChangePasswordData $data, Request $request): AuthResponse;

    /**
     * Gives an account without a password (made by social sign-in) its first one. No current password
     * exists to ask for, so this proves nothing (a stolen session could do it): the password is stamped
     * `password_set_without_proof_at` and the address gets a notice. Sessions are rotated as in
     * changePassword(). Returns the new session for the controller's cookies.
     *
     * @throws PasswordAlreadySet
     */
    public function setPassword(User $user, SetPasswordData $data, Request $request): AuthResponse;

    /**
     * Called by the password-reset flow once a reset through the emailed link succeeded. That proves
     * control of the inbox, so a password first chosen without proof now counts as proof for email
     * changes, an unverified address becomes verified, and any pending email change is dropped.
     */
    public function passwordResetThroughEmail(User $user): void;

    /** Stores the image on the public disk (its path, not its URL, goes in the row), then removes the previous one. */
    public function uploadAvatar(User $user, UploadedFile $file): AccountResponse;

    public function deleteAvatar(User $user): AccountResponse;

    /**
     * Unlinks a provider (nothing happens when it is not linked). An account without a password
     * cannot unlink its last provider, since nothing else would let it sign in.
     *
     * @throws CannotDisconnectLastLogin
     */
    public function disconnectSocial(User $user, string $provider): AccountResponse;

    /**
     * Removes the stored avatar file, the account and everything it owns, with no further check: callers
     * (AccountDeletionServiceInterface, after a password or an emailed link) prove ownership first.
     */
    public function deleteAccount(User $user): void;
}
