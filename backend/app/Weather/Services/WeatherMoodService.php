<?php

namespace App\Weather\Services;

use App\Gemini\Exceptions\GeminiUnavailable;
use App\Gemini\InterfaceServices\GeminiServiceInterface;
use App\Weather\DTOs\Requests\WeatherMoodQuery;
use App\Weather\DTOs\Responses\WeatherMoodResponse;
use App\Weather\Exceptions\WeatherMoodUnavailable;
use App\Weather\InterfaceServices\WeatherMoodServiceInterface;

class WeatherMoodService implements WeatherMoodServiceInterface
{
    public function __construct(private readonly GeminiServiceInterface $gemini) {}

    public function mood(WeatherMoodQuery $query): WeatherMoodResponse
    {
        try {
            $result = $this->gemini->ask($this->prompt($query));
        } catch (GeminiUnavailable $e) {
            report($e);

            throw new WeatherMoodUnavailable;
        }

        return new WeatherMoodResponse($result['mood'] ?? '', $result['description'] ?? '');
    }

    private function prompt(WeatherMoodQuery $query): string
    {
        return 'You are a movie mood curator. '
            ."The current weather is: {$query->temperature}°C, {$query->condition}, "
            ."humidity {$query->humidity}%, wind {$query->wind} km/h, "
            ."precipitation {$query->precipitation} mm. "
            .'Decide the movie mood this weather calls for. '
            ."'mood' must be a short genre phrase, max 6 words (example style: Cozy thrillers & comfort dramas). "
            ."'description' must be 1-2 short sentences linking weather types to picks "
            .'(example style: Rainy evenings → slower, atmospheric picks. Sunny weekends → feel-good & adventure.). '
            .'Do not copy the examples, base it on the current weather. '
            .'Return ONLY valid JSON in this format: {"mood":"","description":""}'
            .'also the max character count for mood should be 30, and the max character count for description should be 60';
    }
}
