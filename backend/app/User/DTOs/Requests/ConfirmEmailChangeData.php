<?php

namespace App\User\DTOs\Requests;

/** The route parameters of a signed email-change link. */
final readonly class ConfirmEmailChangeData
{
    public function __construct(
        public int $userId,
        public string $hash,
    ) {}
}
