<?php

namespace App\Admin\DTOs\Responses;

use App\Genre\DTOs\Responses\GenreResponse;
use App\Models\GenreOverride;
use JsonSerializable;

/** One TMDB genre merged with its local override, as the genres page lists it. */
final readonly class AdminGenreResponse implements JsonSerializable
{
    public function __construct(
        public int $tmdbId,
        public string $tmdbName,
        public ?string $nameOverride,
        public bool $isHidden,
        public ?int $position,
        public int $movieCount,
        public ?string $updatedAt,
    ) {}

    public static function merge(GenreResponse $tmdb, ?GenreOverride $override, int $movieCount): self
    {
        return new self(
            tmdbId: $tmdb->id,
            tmdbName: $tmdb->name,
            nameOverride: $override?->name,
            isHidden: $override?->is_hidden ?? false,
            position: $override?->position,
            movieCount: $movieCount,
            updatedAt: $override?->updated_at?->toJSON(),
        );
    }

    /**
     * @return array{tmdb_id: int, tmdb_name: string, name: string, name_override: string|null, is_hidden: bool, position: int|null, movie_count: int, updated_at: string|null}
     */
    public function jsonSerialize(): array
    {
        return [
            'tmdb_id' => $this->tmdbId,
            'tmdb_name' => $this->tmdbName,
            'name' => $this->nameOverride ?? $this->tmdbName,
            'name_override' => $this->nameOverride,
            'is_hidden' => $this->isHidden,
            'position' => $this->position,
            'movie_count' => $this->movieCount,
            'updated_at' => $this->updatedAt,
        ];
    }
}
