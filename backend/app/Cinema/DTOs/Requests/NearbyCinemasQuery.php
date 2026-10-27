<?php

namespace App\Cinema\DTOs\Requests;

final readonly class NearbyCinemasQuery
{
    public function __construct(public float $latitude, public float $longitude) {}

    /** @param array{latitude: int|float|string, longitude: int|float|string} $validated */
    public static function fromValidated(array $validated): self
    {
        return new self((float) $validated['latitude'], (float) $validated['longitude']);
    }
}
