<?php

namespace App\Admin\DTOs\Responses;

use App\Models\LocalMovie;
use JsonSerializable;

/** A film in the local catalogue as the admin movie table shows it. */
final readonly class AdminMovieResponse implements JsonSerializable
{
    /**
     * @param  list<int>  $genreIds
     */
    public function __construct(
        public int $tmdbId,
        public string $title,
        public ?string $overview,
        public ?string $posterPath,
        public ?string $releaseDate,
        public ?int $runtime,
        public array $genreIds,
        public bool $isHidden,
        public bool $isFeatured,
        public ?string $syncedAt,
    ) {}

    public static function fromModel(LocalMovie $movie): self
    {
        return new self(
            tmdbId: $movie->tmdb_id,
            title: $movie->title,
            overview: $movie->overview,
            posterPath: $movie->poster_path,
            releaseDate: $movie->release_date?->toDateString(),
            runtime: $movie->runtime,
            genreIds: $movie->genre_ids ?? [],
            isHidden: $movie->is_hidden,
            isFeatured: $movie->is_featured,
            syncedAt: $movie->synced_at?->toJSON(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'tmdb_id' => $this->tmdbId,
            'title' => $this->title,
            'overview' => $this->overview,
            'poster_path' => $this->posterPath,
            'release_date' => $this->releaseDate,
            'runtime' => $this->runtime,
            'genre_ids' => $this->genreIds,
            'is_hidden' => $this->isHidden,
            'is_featured' => $this->isFeatured,
            'synced_at' => $this->syncedAt,
        ];
    }
}
