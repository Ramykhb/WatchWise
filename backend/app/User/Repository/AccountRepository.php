<?php

namespace App\User\Repository;

use App\Models\User;
use App\Models\UserPreference;
use App\User\InterfaceRepository\AccountRepositoryInterface;

class AccountRepository implements AccountRepositoryInterface
{
    public function updateProfile(User $user, array $attributes): void
    {
        $user->fill($attributes)->save();
    }

    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function emailBelongsToAnotherUser(string $email, int $userId): bool
    {
        return User::where('email', $email)->whereKeyNot($userId)->exists();
    }

    public function updatePendingEmail(User $user, ?string $email): void
    {
        $user->forceFill(['pending_email' => $email])->save();
    }

    public function applyPendingEmail(User $user): void
    {
        $user->forceFill([
            'email' => $user->pending_email,
            'email_verified_at' => now(),
            'pending_email' => null,
        ])->save();
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
