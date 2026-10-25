<?php

namespace App\User\InterfaceServices;

use App\Models\User;
use App\User\DTOs\Requests\ChangePasswordData;
use App\User\DTOs\Requests\DeleteAccountData;
use App\User\DTOs\Requests\UpdatePreferencesData;
use App\User\DTOs\Requests\UpdateProfileData;
use App\User\DTOs\Responses\AccountResponse;
use App\User\Exceptions\IncorrectPassword;
use Illuminate\Http\UploadedFile;

interface AccountServiceInterface
{
    public function show(User $user): AccountResponse;

    /** Changing the email address marks it unverified and sends a new verification mail. */
    public function updateProfile(User $user, UpdateProfileData $data): AccountResponse;

    /** Merges the switches that were sent into the saved settings; unknown keys are ignored. */
    public function updatePreferences(User $user, UpdatePreferencesData $data): AccountResponse;

    /**
     * Signs out every other device by revoking their refresh tokens.
     *
     * @throws IncorrectPassword
     */
    public function changePassword(User $user, ChangePasswordData $data): void;

    /** Stores the image on the public disk and replaces the previous one. */
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
