<?php

namespace App\MovieRecommendation\DTOs\Responses;

use JsonSerializable;

/** Recommended TMDB titles and the context behind each selection. */
final readonly class RecommendationResponse implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->payload;
    }
}
