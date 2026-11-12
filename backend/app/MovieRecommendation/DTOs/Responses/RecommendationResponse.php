<?php

namespace App\MovieRecommendation\DTOs\Responses;

use App\Support\HiddenMovies;
use App\Support\MediaType;
use JsonSerializable;

/** Recommended TMDB titles and the context behind each selection. */
final readonly class RecommendationResponse implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

    /**
     * The same recommendations without the hidden movies. Applied to a payload read back from the cache, so
     * hiding a film takes effect at once instead of when the cached picks expire.
     */
    public function without(HiddenMovies $hidden): self
    {
        $movies = array_values(array_filter(
            $this->payload['movies'] ?? [],
            fn (array $movie): bool => ! $hidden->hides($movie['media_type'] ?? MediaType::MOVIE, $movie['id']),
        ));

        return new self([...$this->payload, 'movies' => $movies]);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->payload;
    }
}
