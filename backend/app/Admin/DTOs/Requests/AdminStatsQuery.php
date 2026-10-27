<?php

namespace App\Admin\DTOs\Requests;

/** How many days of the activity chart the dashboard shows. */
final readonly class AdminStatsQuery
{
    public const DEFAULT_DAYS = 30;

    public const MAX_DAYS = 90;

    public function __construct(public int $days = self::DEFAULT_DAYS) {}

    /**
     * @param  array{days?: int|string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self((int) ($validated['days'] ?? self::DEFAULT_DAYS));
    }
}
