<?php

namespace App\MovieRecommendation\DTOs\Responses;

use JsonSerializable;

/** The TMDB movies selected by AI, with the reason for each selection. */
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
