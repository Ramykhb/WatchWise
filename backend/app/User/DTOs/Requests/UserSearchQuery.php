<?php

namespace App\User\DTOs\Requests;

final readonly class UserSearchQuery
{
    public const DEFAULT_LIMIT = 10;

    public const MAX_LIMIT = 30;

    public function __construct(
        public string $q = '',
        public int $limit = self::DEFAULT_LIMIT,
    ) {}

    /** Out-of-range limits are clamped instead of rejected, so the header search never errors. */
    public static function fromInput(mixed $q, mixed $limit): self
    {
        return new self(
            trim((string) ($q ?? '')),
            min(max((int) ($limit ?? self::DEFAULT_LIMIT), 1), self::MAX_LIMIT),
        );
    }
}
