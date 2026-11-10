<?php

namespace App\User\Repository;

use App\Models\User;
use App\Models\UserPreference;
use App\User\InterfaceRepository\AccountRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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
            'pending_email_link_sent_at' => now(),
        ])->save();
    }

    public function authorizePendingEmail(User $user, string $nonce): bool
    {
        $now = now();
        $updated = $this->samePendingRequest($user, $nonce)
            ->whereNull('pending_email_authorized_at')
            ->update(['pending_email_authorized_at' => $now, 'pending_email_link_sent_at' => $now]);

        if ($updated === 1) {
            $user->forceFill(['pending_email_authorized_at' => $now, 'pending_email_link_sent_at' => $now])
                ->syncOriginalAttributes(['pending_email_authorized_at', 'pending_email_link_sent_at']);
        }

        return $updated === 1;
    }

    public function clearPendingEmail(User $user, ?string $nonce = null): bool
    {
        $cleared = ['pending_email' => null, 'pending_email_nonce' => null, 'pending_email_authorized_at' => null, 'pending_email_link_sent_at' => null];
        $query = $nonce === null ? User::query()->whereKey($user->id) : $this->samePendingRequest($user, $nonce);
        $updated = $query->update($cleared);

        if ($updated === 1) {
            $user->forceFill($cleared)->syncOriginalAttributes(array_keys($cleared));
        }

        return $updated === 1;
    }

    public function markPendingEmailLinkSent(User $user): bool
    {
        $now = now();

        $updated = User::query()
            ->whereKey($user->id)
            ->whereNotNull('pending_email')
            ->where('pending_email_nonce', $user->pending_email_nonce)
            ->update(['pending_email_link_sent_at' => $now]);

        if ($updated === 1) {
            $user->forceFill(['pending_email_link_sent_at' => $now])->syncOriginalAttribute('pending_email_link_sent_at');
        }

        return $updated === 1;
    }

    public function clearExpiredPendingEmails(CarbonInterface $cutoff): int
    {
        // A pending address with no stamp (made before the column existed) gets a fresh window, not a purge.
        User::query()
            ->whereNotNull('pending_email')
            ->whereNull('pending_email_link_sent_at')
            ->toBase()
            ->update(['pending_email_link_sent_at' => now()]);

        return User::query()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $stale) => $stale->whereNotNull('pending_email')->where('pending_email_link_sent_at', '<', $cutoff))
                ->orWhere(fn (Builder $leftover) => $leftover->whereNull('pending_email')->where(fn (Builder $any) => $any
                    ->whereNotNull('pending_email_nonce')
                    ->orWhereNotNull('pending_email_authorized_at')
                    ->orWhereNotNull('pending_email_link_sent_at'))))
            ->toBase()
            ->update(['pending_email' => null, 'pending_email_nonce' => null, 'pending_email_authorized_at' => null, 'pending_email_link_sent_at' => null]);
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
            'pending_email_link_sent_at' => null,
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

    public function updateAvatar(User $user, ?string $avatar): void
    {
        $user->forceFill(['avatar' => $avatar])->save();
    }

    public function deleteSocialAccounts(User $user, string $provider): void
    {
        $user->socialAccounts()->where('provider', $provider)->delete();
    }

    public function deleteSocialAccountsKeepingAnother(User $user, string $provider): bool
    {
        return DB::transaction(function () use ($user, $provider): bool {
            // Serialises disconnects for this account, so two at once cannot each see the other's row.
            User::query()->whereKey($user->id)->lockForUpdate()->first(['id']);

            $connected = $user->socialAccounts()->where('provider', $provider)->exists();
            $another = $user->socialAccounts()->where('provider', '!=', $provider)->exists();

            if ($connected && ! $another) {
                return false;
            }

            $user->socialAccounts()->where('provider', $provider)->delete();

            return true;
        });
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
