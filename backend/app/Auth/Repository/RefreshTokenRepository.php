<?php

namespace App\Auth\Repository;

use App\Auth\InterfaceRepository\RefreshTokenRepositoryInterface;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function create(array $attributes): RefreshToken
    {
        return RefreshToken::create($attributes);
    }

    public function findByHash(string $tokenHash): ?RefreshToken
    {
        return RefreshToken::where('token_hash', $tokenHash)->first();
    }

    public function findByHashForUpdate(string $tokenHash): ?RefreshToken
    {
        return RefreshToken::where('token_hash', $tokenHash)->lockForUpdate()->first();
    }

    public function findUserForUpdate(int $userId): ?User
    {
        return User::query()->whereKey($userId)->lockForUpdate()->first();
    }

    public function markRotated(RefreshToken $token, int $replacedById): void
    {
        $token->update(['revoked_at' => now(), 'replaced_by' => $replacedById]);
    }

    public function revokeFamily(string $familyId): void
    {
        RefreshToken::where('family_id', $familyId)->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    public function revokeAllForUser(User $user): int
    {
        return DB::transaction(function () use ($user): int {
            // The increment comes first: it write-locks the user's row until commit, so the value read back right
            // after it is the one this call produced, never a later revocation's, and a refresh rotation (which
            // locks the same row first) either finished before it, so the token it created is revoked below, or
            // waits for this commit and then finds its token revoked.
            User::query()->whereKey($user->id)->toBase()->increment('session_version');
            $version = (int) User::query()->whereKey($user->id)->lockForUpdate()->value('session_version');

            RefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);

            $user->setAttribute('session_version', $version)->syncOriginalAttribute('session_version');

            return $version;
        });
    }
}
