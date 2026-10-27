<?php

namespace App\Cinema\DTOs\Responses;

use JsonSerializable;

final readonly class CinemaResponse implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        public string $address,
        public float $latitude,
        public float $longitude,
        public int $distanceMeters,
        public ?string $website,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'distance_meters' => $this->distanceMeters,
            'website' => $this->website,
        ];
    }
}
