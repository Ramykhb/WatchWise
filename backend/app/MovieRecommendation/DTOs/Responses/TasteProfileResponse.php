<?php

namespace App\MovieRecommendation\DTOs\Responses;

use JsonSerializable;

final readonly class TasteProfileResponse implements JsonSerializable
{
    public function __construct(
        public int $watchedMovies,
        public int $watchedTv,
        public int $fiveStarRatings,
        public int $followingCount,
        public array $genres,
        public ?array $director,
        public ?array $actor,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'watched_movies' => $this->watchedMovies, 'watched_tv' => $this->watchedTv,
            'five_star_ratings' => $this->fiveStarRatings, 'following_count' => $this->followingCount,
            'genres' => $this->genres, 'director' => $this->director, 'actor' => $this->actor,
        ];
    }
}
