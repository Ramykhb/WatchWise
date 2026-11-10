<?php

namespace App\User\DTOs\Responses;

/** How many accounts a cleanup run released from an expired pending email change or deletion request. */
final readonly class PrunedPendingAccountData
{
    public function __construct(
        public int $emailChanges,
        public int $deletionRequests,
    ) {}
}
