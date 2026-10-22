<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MovieRecommendationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // 1. Location (defaults to Beirut, same as your weather endpoint)
        $latitude = $request->query('latitude', 33.8938);
        $longitude = $request->query('longitude', 35.5018);

        // 2. Get the current weather
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

        // 3. Turn the weather code into words
        $description = match (true) {
            $current['weather_code'] === 0 => 'clear sky',
            $current['weather_code'] <= 3 => 'partly cloudy',
            $current['weather_code'] <= 48 => 'foggy',
            $current['weather_code'] <= 67 => 'rainy',
            $current['weather_code'] <= 77 => 'snowy',
            $current['weather_code'] <= 82 => 'rain showers',
            default => 'stormy',
        };

        // 4. Build the prompt
        $prompt = "You are a movie recommendation expert. "
            . "The current weather is: {$current['temperature_2m']}°C, {$description}, "
            . "humidity {$current['relative_humidity_2m']}%, wind {$current['wind_speed_10m']} km/h. "
            . "Recommend exactly 10 good movies that fit this weather and mood. "
            . "Return ONLY valid JSON, no markdown, no extra text, in this format: "
            . '{"movies":[{"title":"","year":0,"genre":"","reason":""}]}';

        // 5. Send it to Gemini
        $geminiResponse = Http::timeout(60)
            ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
            ->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent', [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                ],
            ]);

            if ($geminiResponse->failed()) {
                return response()->json([
                    'error' => 'Gemini request failed',
                    'status' => $geminiResponse->status(),
                    'details' => $geminiResponse->json(),
                ], 502);
            }

        // 6. Dig out the text and decode it
        $text = $geminiResponse->json('candidates.0.content.parts.0.text');
        $data = json_decode($text, true);

        if (! is_array($data)) {
            return response()->json(['error' => 'Gemini returned invalid JSON'], 502);
        }

        return response()->json($data);
    }
}