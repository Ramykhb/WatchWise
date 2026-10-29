<?php

namespace App\MovieList\DTOs\Requests;

final readonly class CreateMovieListData
{
    /**
     * @param  list<ListFilmData>  $films  movies and TV shows, in list order
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public bool $isPublic,
        public array $films,
    ) {}

    /**
     * @param  array{title: string, description?: string|null, is_public?: bool, tmdb_ids?: list<int|string>}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            $validated['title'],
            $validated['description'] ?? null,
            $validated['is_public'] ?? false,
            array_map(fn (int|string $key): ListFilmData => ListFilmData::fromKey($key), $validated['tmdb_ids'] ?? []),
        );
    }
}
