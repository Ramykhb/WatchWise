<?php

namespace App\Weather\DTOs\Requests;

/** The weather the frontend already has, to be turned into a movie mood. */
final readonly class WeatherMoodQuery
{
    public function __construct(
        public string $condition,
        public float $temperature,
        public float $humidity,
        public float $wind,
        public float $precipitation,
    ) {}

    /**
     * @param  array{condition: string, temperature: int|float|string, humidity: int|float|string, wind: int|float|string, precipitation: int|float|string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            $validated['condition'],
            (float) $validated['temperature'],
            (float) $validated['humidity'],
            (float) $validated['wind'],
            (float) $validated['precipitation'],
        );
    }
}
