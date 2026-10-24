<?php

namespace App\MovieRecommendation\DTOs\Responses;

use JsonSerializable;

/** The movies Gemini recommended, in its own shape: {"movies":[{"title","year","genre","reason"}]}. */
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
