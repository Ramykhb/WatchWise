<?php

namespace App\Auth\InterfaceRepository;

use App\Models\RefreshToken;
use App\Models\User;

interface RefreshTokenRepositoryInterface
{
    /**
     * @param  array{user_id: int, token_hash: string, family_id: string, expires_at: \DateTimeInterface, user_agent?: string|null, ip?: string|null}  $attributes
     */
    public function create(array $attributes): RefreshToken;

    public function findByHash(string $tokenHash): ?RefreshToken;

    /** Same as findByHash but locks the row; call inside a transaction. */
    public function findByHashForUpdate(string $tokenHash): ?RefreshToken;

    /**
     * The token's owner, row-locked until the transaction ends (call inside one). Rotation takes this lock
     * before re-reading its token, the same row revokeAllForUser() locks first, so the two never interleave.
     */
    public function findUserForUpdate(int $userId): ?User;

    public function markRotated(RefreshToken $token, int $replacedById): void;

    public function revokeFamily(string $familyId): void;

    /**
     * Revokes every refresh token of the user and raises `users.session_version` in one transaction, so
     * every access token issued with the previous version stops working too (CurrentUserFactory only accepts
     * an exact match). Returns the new version, also set on the given model, so a flow that keeps the calling
     * device signed in issues its new tokens with it. Every "sign out everywhere" path goes through here.
     */
    public function revokeAllForUser(User $user): int;
}
