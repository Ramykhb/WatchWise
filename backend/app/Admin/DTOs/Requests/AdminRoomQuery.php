<?php

namespace App\Admin\DTOs\Requests;

/** One page of the discussion rooms staff review, optionally filtered by text, visibility, creator, film, lock or open reports. */
final readonly class AdminRoomQuery
{
    public const VISIBILITIES = ['public', 'private'];

    public const MAX_PER_PAGE = 50;

    /**
     * @param  'public'|'private'|null  $visibility
     */
    public function __construct(
        public string $search = '',
        public ?string $visibility = null,
        public ?int $creatorId = null,
        public ?int $tmdbId = null,
        public ?bool $locked = null,
        public ?bool $reported = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {}

    /**
     * @param  array{q?: string|null, visibility?: string|null, creator_id?: int|string|null, tmdb_id?: int|string|null, locked?: bool|int|string|null, reported?: bool|int|string|null, page?: int|string, per_page?: int|string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            trim((string) ($validated['q'] ?? '')),
            $validated['visibility'] ?? null,
            isset($validated['creator_id']) ? (int) $validated['creator_id'] : null,
            isset($validated['tmdb_id']) ? (int) $validated['tmdb_id'] : null,
            isset($validated['locked']) ? (bool) $validated['locked'] : null,
            isset($validated['reported']) ? (bool) $validated['reported'] : null,
            max(1, (int) ($validated['page'] ?? 1)),
            min(self::MAX_PER_PAGE, max(1, (int) ($validated['per_page'] ?? 20))),
        );
    }
}
