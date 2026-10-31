<?php

namespace App\User\Services;

use App\Auth\InterfaceRepository\RefreshTokenRepositoryInterface;
use App\Auth\InterfaceServices\EmailVerificationServiceInterface;
use App\Models\User;
use App\Support\SocialPlaceholderPassword;
use App\User\DTOs\Requests\ChangePasswordData;
use App\User\DTOs\Requests\DeleteAccountData;
use App\User\DTOs\Requests\UpdatePreferencesData;
use App\User\DTOs\Requests\UpdateProfileData;
use App\User\DTOs\Responses\AccountResponse;
use App\User\Exceptions\IncorrectPassword;
use App\User\InterfaceRepository\AccountRepositoryInterface;
use App\User\InterfaceServices\AccountServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AccountService implements AccountServiceInterface
{
    public function __construct(
        private readonly AccountRepositoryInterface $accounts,
        private readonly EmailVerificationServiceInterface $verification,
        private readonly RefreshTokenRepositoryInterface $tokens,
    ) {}

    public function show(User $user): AccountResponse
    {
        return AccountResponse::fromModel($user);
    }

    public function updateProfile(User $user, UpdateProfileData $data): AccountResponse
    {
        $email = $data->attributes['email'] ?? null;
        $emailChanged = $email !== null && strcasecmp($email, $user->email) !== 0;

        $this->accounts->updateProfile($user, $data->attributes, $emailChanged);

        if ($emailChanged) {
            $this->verification->send($user);
        }

        return AccountResponse::fromModel($user->refresh());
    }

    public function updatePreferences(User $user, UpdatePreferencesData $data): AccountResponse
    {
        $preferences = $this->accounts->findOrNewPreferences($user);
        $settings = $preferences->settings ?? [];

        foreach ($data->groups() as $group => [$sent, $allowedKeys]) {
            $settings[$group] = [...($settings[$group] ?? []), ...array_intersect_key($sent, array_flip($allowedKeys))];
        }

        $preferences->settings = $settings;
        if ($data->favoriteGenres !== null) {
            $preferences->favorite_genres = array_values($data->favoriteGenres);
        }
        $this->accounts->savePreferences($preferences);

        return AccountResponse::fromModel($user->refresh());
    }

    public function changePassword(User $user, ChangePasswordData $data): void
    {
        // Accounts created by social sign-in still hold the placeholder, so they set a first password freely.
        if (! SocialPlaceholderPassword::isStoredFor($user)) {
            if ($data->currentPassword === null) {
                throw IncorrectPassword::forMissingCurrentPassword();
            }
            if (! Hash::check($data->currentPassword, $user->password)) {
                throw IncorrectPassword::forCurrentPassword();
            }
        }

        $this->accounts->updatePassword($user, $data->newPassword);
        // The access cookie stays valid until it expires (15 min); every refresh token is gone.
        $this->tokens->revokeAllForUser($user->id);
    }

    public function uploadAvatar(User $user, UploadedFile $file): AccountResponse
    {
        $this->deleteStoredAvatar($user);
        $path = $file->store('avatars', 'public');
        $this->accounts->updateAvatar($user, Storage::disk('public')->url($path));

        return AccountResponse::fromModel($user);
    }

    public function deleteAvatar(User $user): AccountResponse
    {
        $this->deleteStoredAvatar($user);
        $this->accounts->updateAvatar($user, null);

        return AccountResponse::fromModel($user);
    }

    public function disconnectSocial(User $user, string $provider): AccountResponse
    {
        $this->accounts->deleteSocialAccounts($user, $provider);

        return AccountResponse::fromModel($user);
    }

    public function deleteAccount(User $user, DeleteAccountData $data): void
    {
        if (! Hash::check($data->password, $user->password)) {
            throw IncorrectPassword::forAccountDeletion();
        }

        $this->deleteStoredAvatar($user);
        $this->accounts->delete($user);
    }

    /** Only files this app stored are removed; an avatar copied from Google or GitHub is just a URL. */
    private function deleteStoredAvatar(User $user): void
    {
        $prefix = Storage::disk('public')->url('avatars/');
        if ($user->avatar && str_starts_with($user->avatar, $prefix)) {
            Storage::disk('public')->delete('avatars/'.substr($user->avatar, strlen($prefix)));
        }
    }
}
