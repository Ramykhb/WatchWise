<?php

namespace App\Tmdb\DTOs\Responses;

use JsonSerializable;

/** The movie fields the frontend needs. */
final readonly class MovieResponse implements JsonSerializable
{
    /**
     * @param  list<int>  $genreIds
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $overview,
        public ?string $releaseDate,
        public ?int $year,
        public float $rating,
        public int $voteCount,
        public array $genreIds,
        public ?string $posterUrl,
        public ?string $backdropUrl,
        /** 'movie' or 'tv'. */
        public string $mediaType = 'movie',
    ) {}

    /**
     * @return array{id: int, media_type: string, title: string, overview: string, release_date: ?string, year: ?int, rating: float, vote_count: int, genre_ids: list<int>, poster_url: ?string, backdrop_url: ?string}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'media_type' => $this->mediaType,
            'title' => $this->title,
            'overview' => $this->overview,
            'release_date' => $this->releaseDate,
            'year' => $this->year,
            'rating' => $this->rating,
            'vote_count' => $this->voteCount,
            'genre_ids' => $this->genreIds,
            'poster_url' => $this->posterUrl,
            'backdrop_url' => $this->backdropUrl,
        ];
    }
}
