<?php

namespace App\Http\Controllers;

use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class WeatherMoodController extends Controller
{
    public function __invoke(Request $request, GeminiService $gemini): JsonResponse
    {
        $weather = $request->validate([
            'condition' => ['required', 'string', 'max:50'],
            'temperature' => ['required', 'numeric'],
            'humidity' => ['required', 'numeric'],
            'wind' => ['required', 'numeric'],
            'precipitation' => ['required', 'numeric'],
        ]);

        $prompt = "You are a movie mood curator. "
            . "The current weather is: {$weather['temperature']}°C, {$weather['condition']}, "
            . "humidity {$weather['humidity']}%, wind {$weather['wind']} km/h, "
            . "precipitation {$weather['precipitation']} mm. "
            . "Decide the movie mood this weather calls for. "
            . "'mood' must be a short genre phrase, max 6 words (example style: Cozy thrillers & comfort dramas). "
            . "'description' must be 1-2 short sentences linking weather types to picks "
            . "(example style: Rainy evenings → slower, atmospheric picks. Sunny weekends → feel-good & adventure.). "
            . "Do not copy the examples, base it on the current weather. "
            . 'Return ONLY valid JSON in this format: {"mood":"","description":""}'
            . 'also the max character count for mood should be 30, and the max character count for description should be 60';

        try {
            $result = $gemini->ask($prompt);
        } catch (RuntimeException $e) {
            return response()->json(['error' => 'Could not generate the weather mood'], 502);
        }

        return response()->json([
            'title' => 'WEATHER MOOD',
            'mood' => $result['mood'] ?? '',
            'description' => $result['description'] ?? '',
        ]);
    }
}