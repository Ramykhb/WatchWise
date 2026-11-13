<?php

namespace App\Rating\DTOs\Requests;

/** Members who left at least `min` star ratings in the last `hours` hours, most first. */
final readonly class RatingBurstQuery
{
    public const MIN_HOURS = 1;

    public const MAX_HOURS = 24;

    public const MIN_RATINGS = 5;

    public const MAX_RATINGS = 500;

    public const MAX_PER_PAGE = 50;

    public function __construct(
        public int $hours = 1,
        public int $min = 20,
        public int $page = 1,
        public int $perPage = 20,
    ) {}

    /**
     * @param  array{hours?: int|string, min?: int|string, page?: int|string, per_page?: int|string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            min(self::MAX_HOURS, max(self::MIN_HOURS, (int) ($validated['hours'] ?? 1))),
            min(self::MAX_RATINGS, max(self::MIN_RATINGS, (int) ($validated['min'] ?? 20))),
            max(1, (int) ($validated['page'] ?? 1)),
            min(self::MAX_PER_PAGE, max(1, (int) ($validated['per_page'] ?? 20))),
        );
    }
}
