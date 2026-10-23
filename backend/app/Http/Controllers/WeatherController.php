<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WeatherController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['required_with:latitude', 'numeric', 'between:-180,180'],
        ]);

        $hasLatitude = array_key_exists('latitude', $validated);

        $latitude = isset($validated['latitude'])
            ? (float) $validated['latitude']
            : config('services.open_meteo.default_latitude');
        $longitude = isset($validated['longitude'])
            ? (float) $validated['longitude']
            : config('services.open_meteo.default_longitude');

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
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Weather service is temporarily unavailable.',
            ], 502);
        }

        return response()->json([
            'location' => [
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
                'is_default' => !$hasLatitude,
            ],
            'current' => $response->json('current'),
        ]);
    }
}
