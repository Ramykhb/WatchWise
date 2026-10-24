<?php

namespace App\Weather\Repository;

use App\Weather\Exceptions\WeatherUnavailable;
use App\Weather\InterfaceRepository\WeatherRepositoryInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class WeatherRepository implements WeatherRepositoryInterface
{
    public function current(float $latitude, float $longitude): ?array
    {
        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get(config('services.open_meteo.url'), [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'current' => implode(',', [
                        'temperature_2m',
                        'relative_humidity_2m',
                        'apparent_temperature',
                        'precipitation',
                        'weather_code',
                        'wind_speed_10m',
                    ]),
                    'timezone' => config('services.open_meteo.timezone'),
                ])
                ->throw();
        } catch (ConnectionException|RequestException) {
            throw new WeatherUnavailable('Open-Meteo request failed');
        }

        return $response->json('current');
    }
}
