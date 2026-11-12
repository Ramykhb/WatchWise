<?php

namespace App\User\DTOs\Requests;

/** The route parameters of a signed email-change link (authorize, confirm or cancel). */
final readonly class EmailChangeLinkData
{
    public function __construct(
        public int $userId,
        public string $hash,
    ) {}
}
