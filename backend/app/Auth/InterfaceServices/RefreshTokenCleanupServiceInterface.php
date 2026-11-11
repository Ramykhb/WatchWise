<?php

namespace App\Auth\InterfaceServices;

interface RefreshTokenCleanupServiceInterface
{
    /** Rows deleted per statement, so one run never holds a long lock on the table. */
    public const CHUNK_SIZE = 1000;

    /**
     * Deletes refresh tokens that expired, or were revoked other than by rotation, more than
     * `jwt.refresh_prune_retention_days` ago (never less than the reuse grace window). A rotated token is kept
     * until it expired that long ago, so replaying it is still caught as reuse. Returns how many were deleted.
     */
    public function pruneStale(): int;
}
