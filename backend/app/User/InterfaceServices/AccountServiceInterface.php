<?php

namespace App\User\InterfaceServices;

use App\Models\User;
use App\User\DTOs\Requests\ChangePasswordData;
use App\User\DTOs\Requests\UpdatePreferencesData;
use App\User\DTOs\Requests\UpdateProfileData;
use App\User\DTOs\Responses\AccountResponse;
use App\User\Exceptions\CannotDisconnectLastLogin;
use App\User\Exceptions\IncorrectPassword;
use Illuminate\Http\UploadedFile;

interface AccountServiceInterface
{
    public function show(User $user): AccountResponse;

    /** Name, handle, location, bio and visibility; the email changes through EmailChangeServiceInterface. */
    public function updateProfile(User $user, UpdateProfileData $data): AccountResponse;

    /** Merges the switches that were sent into the saved settings; unknown keys are ignored. */
    public function updatePreferences(User $user, UpdatePreferencesData $data): AccountResponse;

    /**
     * Signs out every other device by revoking their refresh tokens.
     *
     * @throws IncorrectPassword
     */
    public function changePassword(User $user, ChangePasswordData $data): void;

    /**
     * Called by the password-reset flow once a reset through the emailed link succeeded. That proves
     * control of the inbox, so a password first chosen without proof now counts as proof for email
     * changes, and any pending email change is dropped.
     */
    public function passwordResetThroughEmail(User $user): void;

    /** Stores the image on the public disk and replaces the previous one. */
    public function uploadAvatar(User $user, UploadedFile $file): AccountResponse;

    public function deleteAvatar(User $user): AccountResponse;

    /**
     * Unlinks a provider (nothing happens when it is not linked). An account that never chose a
     * password of its own (it still holds the social placeholder) cannot unlink its last provider,
     * since password sign-in refuses the placeholder and nothing else would let it in.
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
