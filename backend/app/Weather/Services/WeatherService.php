<?php

namespace App\Weather\Services;

use App\Weather\DTOs\Requests\WeatherQuery;
use App\Weather\DTOs\Responses\WeatherResponse;
use App\Weather\InterfaceRepository\WeatherRepositoryInterface;
use App\Weather\InterfaceServices\WeatherServiceInterface;

class WeatherService implements WeatherServiceInterface
{
    public function __construct(private readonly WeatherRepositoryInterface $weather) {}

    public function current(WeatherQuery $query): WeatherResponse
    {
        $latitude = $query->latitude ?? (float) config('services.open_meteo.default_latitude');
        $longitude = $query->longitude ?? (float) config('services.open_meteo.default_longitude');

        return new WeatherResponse(
            $latitude,
            $longitude,
            $query->latitude === null,
            $this->weather->current($latitude, $longitude),
        );
    }
}
