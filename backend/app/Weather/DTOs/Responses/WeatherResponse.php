<?php

namespace App\Weather\DTOs\Responses;

use JsonSerializable;

/** The current weather at a location. `current` is Open-Meteo's own block, passed through as is. */
final readonly class WeatherResponse implements JsonSerializable
{
    /**
     * @param  array<string, mixed>|null  $current
     */
    public function __construct(
        public float $latitude,
        public float $longitude,
        public bool $isDefault,
        public ?array $current,
    ) {}

    /**
     * @return array{location: array{latitude: float, longitude: float, is_default: bool}, current: array<string, mixed>|null}
     */
    public function jsonSerialize(): array
    {
        return [
            'location' => [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'is_default' => $this->isDefault,
            ],
            'current' => $this->current,
        ];
    }
}
