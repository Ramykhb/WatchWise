<?php

namespace App\Auth\InterfaceRepository;

use App\Models\RefreshToken;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;

interface RefreshTokenRepositoryInterface
{
    /**
     * @param  array{user_id: int, token_hash: string, family_id: string, family_started_at: DateTimeInterface, expires_at: DateTimeInterface, user_agent?: string|null, ip?: string|null}  $attributes
     */
    public function create(array $attributes): RefreshToken;

    public function findByHash(string $tokenHash): ?RefreshToken;

    /** Same as findByHash but locks the row; call inside a transaction. */
    public function findByHashForUpdate(string $tokenHash): ?RefreshToken;

    /** The token with this id, row-locked until the transaction ends (call inside one). */
    public function findByIdForUpdate(int $id): ?RefreshToken;

    /**
     * The token's owner, row-locked until the transaction ends (call inside one). Rotation and logout take this
     * lock before re-reading or revoking their token, the same row revokeAllForUser() locks first, so they never
     * interleave.
     */
    public function findUserForUpdate(int $userId): ?User;

    public function markRotated(RefreshToken $token, int $replacedById): void;

    /** Records that this rotated token used its one reuse grace. */
    public function markGraceUsed(RefreshToken $token): void;

    /** The earliest `created_at` among the family's remaining rows, or null when there is none. */
    public function earliestCreatedAtInFamily(string $familyId): ?CarbonImmutable;

    public function revokeFamily(string $familyId): void;

    /**
     * Revokes every refresh token of the user and raises `users.session_version` in one transaction, so
     * every access token issued with the previous version stops working too (CurrentUserFactory only accepts
     * an exact match). Returns the new version, also set on the given model, so a flow that keeps the calling
     * device signed in issues its new tokens with it. Every "sign out everywhere" path goes through here.
     */
    public function revokeAllForUser(User $user): int;

    /**
     * Deletes, `$chunkSize` rows per statement, every token that expired before `$cutoff`, and every token revoked
     * before `$cutoff` other than by rotation (no `replaced_by`). A rotated token is kept until it expires, so
     * replaying it is still detected as reuse. Returns how many rows were deleted.
     */
    public function deleteExpiredOrRevokedBefore(DateTimeInterface $cutoff, int $chunkSize): int;
}
