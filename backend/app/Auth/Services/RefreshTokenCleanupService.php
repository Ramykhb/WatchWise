<?php

namespace App\Auth\Services;

use App\Auth\InterfaceRepository\RefreshTokenRepositoryInterface;
use App\Auth\InterfaceServices\RefreshTokenCleanupServiceInterface;

class RefreshTokenCleanupService implements RefreshTokenCleanupServiceInterface
{
    public function __construct(private readonly RefreshTokenRepositoryInterface $refreshTokens) {}

    public function pruneStale(): int
    {
        // A rotated token must outlive the reuse grace window, or a graced second refresh would find no row.
        $retentionSeconds = max(
            (int) config('jwt.refresh_prune_retention_days') * 86400,
            (int) config('jwt.refresh_reuse_grace_seconds'),
        );

        return $this->refreshTokens->deleteExpiredOrRevokedBefore(now()->subSeconds($retentionSeconds), self::CHUNK_SIZE);
    }
}
