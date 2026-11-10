<?php

namespace App\User\DTOs\Requests;

final readonly class UserSearchQuery
{
    public const DEFAULT_LIMIT = 10;

    public const MAX_LIMIT = 30;

    public const MAX_QUERY_LENGTH = 100;

    public function __construct(
        public string $q = '',
        public int $limit = self::DEFAULT_LIMIT,
    ) {}

    /**
     * Bad input is repaired instead of rejected, so the header search never errors: a `q` that is not
     * plain text (`q[]=x`) counts as empty, a long one is cut to MAX_QUERY_LENGTH characters, and a
     * `limit` that is missing, not a number or out of range falls back to the default or is clamped.
     */
    public static function fromInput(mixed $q, mixed $limit): self
    {
        return new self(
            is_string($q) ? mb_substr(trim($q), 0, self::MAX_QUERY_LENGTH) : '',
            is_numeric($limit) ? min(max((int) $limit, 1), self::MAX_LIMIT) : self::DEFAULT_LIMIT,
        );
    }
}
