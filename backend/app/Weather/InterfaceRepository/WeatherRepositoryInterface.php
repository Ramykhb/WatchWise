<?php

namespace App\Weather\InterfaceRepository;

use App\Weather\Exceptions\WeatherUnavailable;

interface WeatherRepositoryInterface
{
    /**
     * Open-Meteo's `current` block for a location (null when the answer has none).
     *
     * @return array<string, mixed>|null
     *
     * @throws WeatherUnavailable
     */
    public function current(float $latitude, float $longitude): ?array;
}
