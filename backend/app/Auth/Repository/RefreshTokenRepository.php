<?php

namespace App\Auth\Repository;

use App\Auth\InterfaceRepository\RefreshTokenRepositoryInterface;
use App\Models\RefreshToken;

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

    public function markRotated(RefreshToken $token, int $replacedById): void
    {
        $token->update(['revoked_at' => now(), 'replaced_by' => $replacedById]);
    }

    public function revokeFamily(string $familyId): void
    {
        RefreshToken::where('family_id', $familyId)->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    public function revokeAllForUser(int $userId): void
    {
        RefreshToken::where('user_id', $userId)->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }
}
