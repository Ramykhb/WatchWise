<?php

namespace App\User\InterfaceServices;

use App\User\DTOs\Responses\PrunedPendingAccountData;

/**
 * Housekeeping for pending changes whose signed links have expired. The links stop working on their own;
 * this only clears the leftover columns so Settings stops showing them and the rows hold no stale nonce.
 */
interface PendingAccountCleanupServiceInterface
{
    /**
     * Clears pending email changes older than `EmailChangeServiceInterface::EXPIRES_MINUTES` and pending deletion
     * requests older than `AccountDeletionServiceInterface::EXPIRES_MINUTES`, measured from the last link
     * mailed. A change or request whose link is still valid is never touched.
     */
    public function pruneExpired(): PrunedPendingAccountData;
}
