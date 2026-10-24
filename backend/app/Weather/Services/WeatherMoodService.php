<?php

namespace App\Weather\Services;

use App\Groq\Exceptions\GroqUnavailable;
use App\Groq\InterfaceServices\GroqServiceInterface;
use App\Weather\DTOs\Requests\WeatherMoodQuery;
use App\Weather\DTOs\Responses\WeatherMoodResponse;
use App\Weather\Exceptions\WeatherMoodUnavailable;
use App\Weather\InterfaceServices\WeatherMoodServiceInterface;
use Illuminate\Support\Str;

class WeatherMoodService implements WeatherMoodServiceInterface
{
    public function __construct(private readonly GroqServiceInterface $groq) {}

    public function mood(WeatherMoodQuery $query): WeatherMoodResponse
    {
        try {
            $result = $this->groq->ask($this->prompt($query));
        } catch (GroqUnavailable $e) {
            report($e);

            throw new WeatherMoodUnavailable;
        }

        return new WeatherMoodResponse(
            is_string($result['mood'] ?? null) ? Str::limit($result['mood'], 30, '') : '',
            is_string($result['description'] ?? null) ? Str::limit($result['description'], 60, '') : '',
        );
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
            .' Keep mood to at most 30 characters and description to at most 60 characters.';
    }
}
