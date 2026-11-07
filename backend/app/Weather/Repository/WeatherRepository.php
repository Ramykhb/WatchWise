<?php

namespace App\Weather\Repository;

use App\Weather\DTOs\Responses\CurrentWeatherResponse;
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
        $key = config('services.weatherapi.key');
        if (! is_string($key) || trim($key) === '') {
            throw new WeatherUnavailable('WeatherAPI.com API key is not configured.');
        }

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get(config('services.weatherapi.url'), [
                    'key' => $key,
                    'q' => $latitude.','.$longitude,
                    'aqi' => 'no',
                ])
                ->throw();
        } catch (ConnectionException|RequestException $error) {
            $upstream = $error instanceof RequestException ? $error->response : null;
            $reason = $upstream?->json('error.message');
            preg_match('/cURL error (\d+)/', $error->getMessage(), $curlError);

            // stderr is visible in Render's Logs tab, even with file logging enabled.
            // Do not log the request URL, coordinates, credentials or response body.
            Log::channel('stderr')->warning('WeatherAPI.com request failed.', [
                'host' => parse_url((string) config('services.weatherapi.url'), PHP_URL_HOST),
                'failure_type' => $upstream === null ? 'connection' : 'http',
                'upstream_status' => $upstream?->status(),
                'upstream_reason' => is_string($reason) ? mb_substr(str_replace($key, '[redacted]', $reason), 0, 200) : null,
                'curl_error' => isset($curlError[1]) ? (int) $curlError[1] : null,
            ]);

            throw new WeatherUnavailable('WeatherAPI.com request failed');
        }

        $data = $response->json();
        $current = is_array($data) ? CurrentWeatherResponse::fromWeatherApi($data) : null;
        if ($current === null) {
            throw new WeatherUnavailable('WeatherAPI.com returned an invalid weather response.');
        }

        return $current->jsonSerialize();
    }
}
