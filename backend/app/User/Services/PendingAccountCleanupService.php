<?php

namespace App\User\Services;

use App\User\DTOs\Responses\PrunedPendingAccountData;
use App\User\InterfaceRepository\AccountDeletionRepositoryInterface;
use App\User\InterfaceRepository\AccountRepositoryInterface;
use App\User\InterfaceServices\AccountDeletionServiceInterface;
use App\User\InterfaceServices\EmailChangeServiceInterface;
use App\User\InterfaceServices\PendingAccountCleanupServiceInterface;

class PendingAccountCleanupService implements PendingAccountCleanupServiceInterface
{
    public function __construct(
        private readonly AccountRepositoryInterface $accounts,
        private readonly AccountDeletionRepositoryInterface $deletions,
    ) {}

    public function pruneExpired(): PrunedPendingAccountData
    {
        // The same lifetimes the signed links are issued with.
        return new PrunedPendingAccountData(
            emailChanges: $this->accounts->clearExpiredPendingEmails(now()->subMinutes(EmailChangeServiceInterface::EXPIRES_MINUTES)),
            deletionRequests: $this->deletions->clearExpiredDeletionRequests(now()->subMinutes(AccountDeletionServiceInterface::EXPIRES_MINUTES)),
        );
    }
}
