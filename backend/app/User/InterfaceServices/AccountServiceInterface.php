<?php

namespace App\User\InterfaceServices;

use App\Auth\DTOs\Responses\AuthResponse;
use App\Models\User;
use App\User\DTOs\Requests\ChangePasswordData;
use App\User\DTOs\Requests\DeleteAccountData;
use App\User\DTOs\Requests\UpdatePreferencesData;
use App\User\DTOs\Requests\UpdateProfileData;
use App\User\DTOs\Responses\AccountResponse;
use App\User\Exceptions\IncorrectPassword;
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
     * Revokes every refresh token the user holds and starts a fresh session for the calling device,
     * so only the other devices are signed out. A FIRST password (the account only had the social
     * placeholder) also mails the address a notice. Returns the new session for the controller's cookies.
     *
     * @throws IncorrectPassword
     */
    public function changePassword(User $user, ChangePasswordData $data, Request $request): AuthResponse;

    /**
     * Called by the password-reset flow once a reset through the emailed link succeeded. That proves
     * control of the inbox, so a password first chosen without proof now counts as proof for email
     * changes, and any pending email change is dropped.
     */
    public function passwordResetThroughEmail(User $user): void;

    /** Stores the image on the public disk (its path, not its URL, goes in the row), then removes the previous one. */
    public function uploadAvatar(User $user, UploadedFile $file): AccountResponse;

    public function deleteAvatar(User $user): AccountResponse;

    /**
     * Accounts created through a provider have a random password, so the owner can still get
     * back in with email and "Forgot password".
     */
    public function disconnectSocial(User $user, string $provider): AccountResponse;

    /**
     * Removes the account and everything it owns.
     *
     * @throws IncorrectPassword
     */
    public function deleteAccount(User $user, DeleteAccountData $data): void;
}
