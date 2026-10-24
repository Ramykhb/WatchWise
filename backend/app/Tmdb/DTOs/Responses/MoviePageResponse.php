<?php

namespace App\Tmdb\DTOs\Responses;

use JsonSerializable;

/** One page of movies, plus the echo field of the endpoint that produced it (list, query or sort). */
final readonly class MoviePageResponse implements JsonSerializable
{
    /**
     * @param  list<MovieResponse>  $movies
     * @param  array<string, string>  $meta  Keys placed before the paging fields, e.g. ['list' => 'popular']
     */
    public function __construct(
        public int $page,
        public int $totalPages,
        public int $totalResults,
        public array $movies,
        public array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            ...$this->meta,
            'page' => $this->page,
            'total_pages' => $this->totalPages,
            'total_results' => $this->totalResults,
            'movies' => $this->movies,
        ];
    }
}
