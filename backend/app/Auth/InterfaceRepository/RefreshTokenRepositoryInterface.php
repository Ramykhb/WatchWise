<?php

namespace App\Auth\InterfaceRepository;

use App\Models\RefreshToken;

interface RefreshTokenRepositoryInterface
{
    /**
     * @param  array{user_id: int, token_hash: string, family_id: string, expires_at: \DateTimeInterface, user_agent?: string|null, ip?: string|null}  $attributes
     */
    public function create(array $attributes): RefreshToken;

    public function findByHash(string $tokenHash): ?RefreshToken;

    /** Same as findByHash but locks the row; call inside a transaction. */
    public function findByHashForUpdate(string $tokenHash): ?RefreshToken;

    public function markRotated(RefreshToken $token, int $replacedById): void;

    public function revokeFamily(string $familyId): void;

    public function revokeAllForUser(int $userId): void;
}
