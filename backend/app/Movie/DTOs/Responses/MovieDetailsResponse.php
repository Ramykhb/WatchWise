<?php

namespace App\Movie\DTOs\Responses;

use App\Genre\DTOs\Responses\GenreResponse;
use JsonSerializable;

/** Everything the Movie Details page needs: the movie's list fields plus its extras. */
final readonly class MovieDetailsResponse implements JsonSerializable
{
    /**
     * @param  list<GenreResponse>  $genres
     * @param  array{code: string, name: string}|null  $originalLanguage
     * @param  list<PersonResponse>  $directors
     * @param  list<CastMemberResponse>  $cast
     * @param  list<MovieResponse>  $similar
     */
    public function __construct(
        public MovieResponse $movie,
        public array $genres,
        public ?string $tagline,
        public ?int $runtime,
        public ?string $status,
        public float $tmdbScore,
        public ?array $originalLanguage,
        public ?string $certification,
        public ?int $budget,
        public ?int $revenue,
        public array $directors,
        public array $cast,
        public ?TrailerResponse $trailer,
        public array $similar,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            ...$this->movie->jsonSerialize(),
            // The details payload lists genres as objects, so the ids come from those.
            'genre_ids' => array_map(fn (GenreResponse $genre): int => $genre->id, $this->genres),
            'genres' => $this->genres,
            'tagline' => $this->tagline,
            'runtime' => $this->runtime,
            'status' => $this->status,
            'tmdb_score' => $this->tmdbScore,
            'original_language' => $this->originalLanguage,
            'certification' => $this->certification,
            'budget' => $this->budget,
            'revenue' => $this->revenue,
            'directors' => $this->directors,
            'cast' => $this->cast,
            'trailer' => $this->trailer,
            'similar' => $this->similar,
        ];
    }
}
