<?php

namespace App\Admin\DTOs\Responses;

use JsonSerializable;

/** One row of a "top movies" table. `average` is only set for the highest-rated ranking. */
final readonly class RankedMovieResponse implements JsonSerializable
{
    public function __construct(
        public int $tmdbId,
        public ?string $title,
        public ?string $posterPath,
        public int $count,
        public ?float $average = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'tmdb_id' => $this->tmdbId,
            'title' => $this->title,
            'poster_path' => $this->posterPath,
            'count' => $this->count,
            'average' => $this->average,
        ];
    }
}
