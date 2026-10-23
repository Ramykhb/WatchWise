<?php

namespace App\Http\Controllers;

use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MovieRecommendationController extends Controller
{
    public function __invoke(Request $request, GeminiService $gemini): JsonResponse
    {
        $latitude = $request->query('latitude', 33.8938);
        $longitude = $request->query('longitude', 35.5018);

        $weatherResponse = Http::get('https://api.open-meteo.com/v1/forecast', [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'current' => 'temperature_2m,relative_humidity_2m,wind_speed_10m,weather_code',
            'timezone' => 'Asia/Beirut',
        ]);

        if ($weatherResponse->failed()) {
            return response()->json(['error' => 'Could not get the weather'], 502);
        }

        $current = $weatherResponse->json('current');

        $description = match (true) {
            $current['weather_code'] === 0 => 'clear sky',
            $current['weather_code'] <= 3 => 'partly cloudy',
            $current['weather_code'] <= 48 => 'foggy',
            $current['weather_code'] <= 67 => 'rainy',
            $current['weather_code'] <= 77 => 'snowy',
            $current['weather_code'] <= 82 => 'rain showers',
            default => 'stormy',
        };

        $prompt = "You are a movie recommendation expert. "
            . "The current weather is: {$current['temperature_2m']}°C, {$description}, "
            . "humidity {$current['relative_humidity_2m']}%, wind {$current['wind_speed_10m']} km/h. "
            . "Recommend exactly 10 good movies that fit this weather and mood. "
            . "Return ONLY valid JSON, no markdown, no extra text, in this format: "
            . '{"movies":[{"title":"","year":0,"genre":"","reason":""}]}';

        try {
            return response()->json($gemini->ask($prompt));
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }
}