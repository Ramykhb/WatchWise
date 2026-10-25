<?php

namespace App\User\Repository;

use App\Models\User;
use App\Models\UserPreference;
use App\User\InterfaceRepository\AccountRepositoryInterface;

class AccountRepository implements AccountRepositoryInterface
{
    public function updateProfile(User $user, array $attributes, bool $unverifyEmail): void
    {
        $user->fill($attributes);

        if ($unverifyEmail) {
            $user->email_verified_at = null;
        }

        $user->save();
    }

    public function findOrNewPreferences(User $user): UserPreference
    {
        return $user->preferences()->firstOrNew();
    }

    public function savePreferences(UserPreference $preferences): void
    {
        $preferences->save();
    }

    public function updatePassword(User $user, string $plainPassword): void
    {
        $user->forceFill(['password' => $plainPassword])->save();
    }

    public function updateAvatar(User $user, ?string $url): void
    {
        $user->forceFill(['avatar' => $url])->save();
    }

    public function deleteSocialAccounts(User $user, string $provider): void
    {
        $user->socialAccounts()->where('provider', $provider)->delete();
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
