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
        public ?string $director,
        public array $genreIds,
        public bool $isHidden,
        public bool $isFeatured,
        public bool $isEdited,
        public ?string $syncedAt,
        public ?string $editedAt,
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
            director: $movie->director,
            genreIds: $movie->genre_ids ?? [],
            isHidden: $movie->is_hidden,
            isFeatured: $movie->is_featured,
            isEdited: $movie->edited_at !== null,
            syncedAt: $movie->synced_at?->toJSON(),
            editedAt: $movie->edited_at?->toJSON(),
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
            'director' => $this->director,
            'genre_ids' => $this->genreIds,
            'is_hidden' => $this->isHidden,
            'is_featured' => $this->isFeatured,
            'is_edited' => $this->isEdited,
            'synced_at' => $this->syncedAt,
            'edited_at' => $this->editedAt,
        ];
    }
}
