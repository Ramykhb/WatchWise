<?php

namespace App\User\Services;

use App\Auth\DTOs\Responses\AuthResponse;
use App\Auth\InterfaceServices\AuthServiceInterface;
use App\Models\User;
use App\Support\SocialPlaceholderPassword;
use App\User\DTOs\Requests\ChangePasswordData;
use App\User\DTOs\Requests\UpdatePreferencesData;
use App\User\DTOs\Requests\UpdateProfileData;
use App\User\DTOs\Responses\AccountResponse;
use App\User\Exceptions\CannotDisconnectLastLogin;
use App\User\Exceptions\IncorrectPassword;
use App\User\InterfaceRepository\AccountRepositoryInterface;
use App\User\InterfaceServices\AccountServiceInterface;
use App\User\InterfaceServices\EmailChangeServiceInterface;
use App\User\Mail\PasswordAddedMail;
use App\User\Mail\SendsMailAfterResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AccountService implements AccountServiceInterface
{
    use SendsMailAfterResponse;

    public function __construct(
        private readonly AccountRepositoryInterface $accounts,
        private readonly AuthServiceInterface $auth,
        private readonly EmailChangeServiceInterface $emailChange,
    ) {}

    public function show(User $user): AccountResponse
    {
        return AccountResponse::fromModel($user);
    }

    public function updateProfile(User $user, UpdateProfileData $data): AccountResponse
    {
        $this->accounts->updateProfile($user, $data->attributes);

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

    public function changePassword(User $user, ChangePasswordData $data, Request $request): AuthResponse
    {
        // Accounts created by social sign-in still hold the placeholder, so they set a first password freely.
        $firstPassword = SocialPlaceholderPassword::isStoredFor($user);

        if (! $firstPassword) {
            if ($data->currentPassword === null) {
                throw IncorrectPassword::forMissingCurrentPassword();
            }
            if (! Hash::check($data->currentPassword, $user->password)) {
                throw IncorrectPassword::forCurrentPassword();
            }
        }

        // A first password proves nothing (a stolen session could set it), so it is marked until the
        // owner proves the inbox. A later change keeps the mark: whoever set the first one knows it.
        $this->accounts->updatePassword($user, $data->newPassword, setWithoutProof: $firstPassword);
        // Its links already stopped working (they are bound to the password hash); this clears Settings too.
        $this->emailChange->discardPendingChange($user);

        if ($firstPassword) {
            $this->mailLater($user->email, new PasswordAddedMail($user->name));
        }

        // Every device loses its refresh token (other devices' access cookies run out within minutes);
        // this one gets a fresh session, so changing the password never signs the person out here.
        return $this->auth->replaceSessions($user, $request);
    }

    public function passwordResetThroughEmail(User $user): void
    {
        if ($user->password_set_without_proof_at !== null) {
            $this->accounts->clearPasswordSetWithoutProof($user);
        }

        $this->emailChange->discardPendingChange($user);
    }

    public function uploadAvatar(User $user, UploadedFile $file): AccountResponse
    {
        $previous = $user->storedAvatarPath();

        // The new file lands first and the old one goes only after the row points at the new one,
        // so a failed store or update never leaves the member without their current picture.
        $path = $file->store('avatars', 'public');

        try {
            $this->accounts->updateAvatar($user, $path);
        } catch (Throwable $e) {
            Storage::disk('public')->delete($path);

            throw $e;
        }

        if ($previous !== null) {
            Storage::disk('public')->delete($previous);
        }

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
        // With a password of its own the account can always sign in again; without one, the last linked
        // provider is its only way in, so it stays until a password is set or another provider is linked.
        if (! SocialPlaceholderPassword::isStoredFor($user)) {
            $this->accounts->deleteSocialAccounts($user, $provider);
        } elseif (! $this->accounts->deleteSocialAccountsKeepingAnother($user, $provider)) {
            throw new CannotDisconnectLastLogin($provider);
        }

        return AccountResponse::fromModel($user);
    }

    public function deleteAccount(User $user): void
    {
        $this->deleteStoredAvatar($user);
        $this->accounts->delete($user);
    }

    /** Only files this app stored are removed; an avatar copied from Google or GitHub is just a URL. */
    private function deleteStoredAvatar(User $user): void
    {
        $path = $user->storedAvatarPath();
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }
}
