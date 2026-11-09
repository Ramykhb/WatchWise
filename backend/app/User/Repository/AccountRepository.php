<?php

namespace App\User\Repository;

use App\Models\User;
use App\Models\UserPreference;
use App\User\InterfaceRepository\AccountRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

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

    public function storePendingEmail(User $user, string $email, string $nonce, bool $authorized): void
    {
        $user->forceFill([
            'pending_email' => $email,
            'pending_email_nonce' => $nonce,
            'pending_email_authorized_at' => $authorized ? now() : null,
        ])->save();
    }

    public function authorizePendingEmail(User $user, string $nonce): bool
    {
        $now = now();
        $updated = $this->samePendingRequest($user, $nonce)
            ->whereNull('pending_email_authorized_at')
            ->update(['pending_email_authorized_at' => $now]);

        if ($updated === 1) {
            $user->forceFill(['pending_email_authorized_at' => $now])->syncOriginalAttribute('pending_email_authorized_at');
        }

        return $updated === 1;
    }

    public function clearPendingEmail(User $user, ?string $nonce = null): bool
    {
        $cleared = ['pending_email' => null, 'pending_email_nonce' => null, 'pending_email_authorized_at' => null];
        $query = $nonce === null ? User::query()->whereKey($user->id) : $this->samePendingRequest($user, $nonce);
        $updated = $query->update($cleared);

        if ($updated === 1) {
            $user->forceFill($cleared)->syncOriginalAttributes(array_keys($cleared));
        }

        return $updated === 1;
    }

    public function applyPendingEmail(User $user, string $nonce): bool
    {
        $now = now();
        $applied = [
            'email' => $user->pending_email,
            'email_verified_at' => $now,
            'pending_email' => null,
            'pending_email_nonce' => null,
            'pending_email_authorized_at' => null,
            'password_set_without_proof_at' => null,
        ];
        $updated = $this->samePendingRequest($user, $nonce)
            ->whereNotNull('pending_email_authorized_at')
            ->update($applied);

        if ($updated === 1) {
            $user->forceFill($applied)->syncOriginalAttributes(array_keys($applied));
        }

        return $updated === 1;
    }

    /**
     * The user's row, only while it still holds this exact pending request and the same password.
     *
     * @return Builder<User>
     */
    private function samePendingRequest(User $user, string $nonce): Builder
    {
        return User::query()
            ->whereKey($user->id)
            ->where('pending_email', $user->pending_email)
            ->where('pending_email_nonce', $nonce)
            ->where('password', $user->getAttributes()['password']);
    }

    public function findOrNewPreferences(User $user): UserPreference
    {
        return $user->preferences()->firstOrNew();
    }

    public function savePreferences(UserPreference $preferences): void
    {
        $preferences->save();
    }

    public function updatePassword(User $user, string $plainPassword, bool $setWithoutProof = false): void
    {
        $attributes = ['password' => $plainPassword];
        if ($setWithoutProof) {
            $attributes['password_set_without_proof_at'] = now();
        }

        $user->forceFill($attributes)->save();
    }

    public function clearPasswordSetWithoutProof(User $user): void
    {
        $user->forceFill(['password_set_without_proof_at' => null])->save();
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
