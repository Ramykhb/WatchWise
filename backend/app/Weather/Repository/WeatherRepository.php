<?php

namespace App\Weather\Repository;

use App\Weather\Exceptions\WeatherUnavailable;
use App\Weather\InterfaceRepository\WeatherRepositoryInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        } catch (ConnectionException|RequestException $error) {
            $upstream = $error instanceof RequestException ? $error->response : null;
            $reason = $upstream?->json('reason');
            preg_match('/cURL error (\d+)/', $error->getMessage(), $curlError);

            // stderr is visible in Render's Logs tab, even with file logging enabled.
            // Do not log the request URL, coordinates, credentials or response body.
            Log::channel('stderr')->warning('Open-Meteo request failed.', [
                'host' => parse_url((string) config('services.open_meteo.url'), PHP_URL_HOST),
                'failure_type' => $upstream === null ? 'connection' : 'http',
                'upstream_status' => $upstream?->status(),
                'upstream_reason' => is_string($reason) ? mb_substr($reason, 0, 200) : null,
                'curl_error' => isset($curlError[1]) ? (int) $curlError[1] : null,
            ]);

            throw new WeatherUnavailable('Open-Meteo request failed');
        }

        return $response->json('current');
    }
}
