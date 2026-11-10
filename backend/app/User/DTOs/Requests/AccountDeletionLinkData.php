<?php

namespace App\User\DTOs\Requests;

/** The route parameters of a signed account-deletion link (confirm or cancel). */
final readonly class AccountDeletionLinkData
{
    public function __construct(
        public int $userId,
        public string $hash,
    ) {}
}
