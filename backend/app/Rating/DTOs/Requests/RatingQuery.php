<?php

namespace App\Rating\DTOs\Requests;

/** Which star-only ratings to list or add up: every filter that is set must match. */
final readonly class RatingQuery
{
    public const MAX_PER_PAGE = 50;

    /**
     * @param  'movie'|'tv'|null  $mediaType
     */
    public function __construct(
        public ?int $tmdbId = null,
        public ?string $mediaType = null,
        public ?int $userId = null,
        public ?int $rating = null,
        public string $search = '',
        public int $page = 1,
        public int $perPage = 20,
    ) {}

    /**
     * @param  array{tmdb_id?: int|string|null, media_type?: string|null, user_id?: int|string|null, rating?: int|string|null, q?: string|null, page?: int|string, per_page?: int|string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            isset($validated['tmdb_id']) ? (int) $validated['tmdb_id'] : null,
            $validated['media_type'] ?? null,
            isset($validated['user_id']) ? (int) $validated['user_id'] : null,
            isset($validated['rating']) ? (int) $validated['rating'] : null,
            trim((string) ($validated['q'] ?? '')),
            max(1, (int) ($validated['page'] ?? 1)),
            min(self::MAX_PER_PAGE, max(1, (int) ($validated['per_page'] ?? 20))),
        );
    }
}
