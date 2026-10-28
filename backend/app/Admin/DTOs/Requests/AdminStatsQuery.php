<?php

namespace App\Admin\DTOs\Requests;

/**
 * The window the dashboard looks at: `days` for the activity chart and the "new in this range" counts. A
 * `range` (7, 30, 90 or year) is a shortcut for it and wins over `days`; `year` runs from 1 January to today.
 */
final readonly class AdminStatsQuery
{
    public const DEFAULT_DAYS = 30;

    public const MAX_DAYS = 90;

    public const RANGES = ['7', '30', '90', 'year'];

    public function __construct(
        public int $days = self::DEFAULT_DAYS,
        public ?string $range = null,
    ) {}

    /**
     * @param  array{days?: int|string, range?: string|int|null}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        $range = isset($validated['range']) ? (string) $validated['range'] : null;

        if ($range !== null) {
            return new self($range === 'year' ? today()->dayOfYear : (int) $range, $range);
        }

        return new self((int) ($validated['days'] ?? self::DEFAULT_DAYS));
    }
}
