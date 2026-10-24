<?php

namespace App\MovieRecommendation\DTOs\Requests;

/** Where to read the weather the recommendations are based on. Both null means the default location. */
final readonly class RecommendationQuery
{
    public function __construct(
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {}

    /**
     * @param  array{latitude?: int|float|string, longitude?: int|float|string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            isset($validated['latitude']) ? (float) $validated['latitude'] : null,
            isset($validated['longitude']) ? (float) $validated['longitude'] : null,
        );
    }
}
