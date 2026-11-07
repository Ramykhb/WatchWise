<?php

namespace App\Weather\InterfaceRepository;

use App\Weather\Exceptions\WeatherUnavailable;

interface WeatherRepositoryInterface
{
    /**
     * Normalized current weather for a location.
     *
     * @return array<string, mixed>|null
     *
     * @throws WeatherUnavailable
     */
    public function current(float $latitude, float $longitude): ?array;
}
