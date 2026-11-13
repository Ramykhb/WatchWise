<?php

namespace App\MovieList\DTOs\Requests;

final readonly class UpdateMovieListData
{
    /**
     * @param  array<string, mixed>  $attributes  only the fields the client sent (title, description, is_public)
     * @param  list<ListFilmData>|null  $films  null leaves the films alone; a list replaces them in that order
     * @param  string|null  $expectedUpdatedAt  the updated_at the client loaded; legacy timestamp check
     * @param  int|null  $expectedRevision  the revision the client loaded; detects writes within the same second
     */
    public function __construct(
        public array $attributes,
        public ?array $films,
        public ?string $expectedUpdatedAt = null,
        public ?int $expectedRevision = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            collect($validated)->only(['title', 'description', 'is_public'])->all(),
            array_key_exists('tmdb_ids', $validated)
                ? array_map(fn (int|string $key): ListFilmData => ListFilmData::fromKey($key), $validated['tmdb_ids'])
                : null,
            $validated['expected_updated_at'] ?? null,
            isset($validated['expected_revision']) ? (int) $validated['expected_revision'] : null,
        );
    }
}
