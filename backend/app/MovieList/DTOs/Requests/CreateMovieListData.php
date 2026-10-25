<?php

namespace App\MovieList\DTOs\Requests;

final readonly class CreateMovieListData
{
    /**
     * @param  list<int>  $tmdbIds
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public bool $isPublic,
        public array $tmdbIds,
    ) {}

    /**
     * @param  array{title: string, description?: string|null, is_public?: bool, tmdb_ids?: list<int>}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            $validated['title'],
            $validated['description'] ?? null,
            $validated['is_public'] ?? false,
            $validated['tmdb_ids'] ?? [],
        );
    }
}
