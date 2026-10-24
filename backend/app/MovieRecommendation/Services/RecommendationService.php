<?php

namespace App\MovieRecommendation\Services;

use App\Gemini\Exceptions\GeminiUnavailable;
use App\Gemini\InterfaceServices\GeminiServiceInterface;
use App\MovieRecommendation\DTOs\Requests\RecommendationQuery;
use App\MovieRecommendation\DTOs\Responses\RecommendationResponse;
use App\MovieRecommendation\Exceptions\RecommendationUnavailable;
use App\MovieRecommendation\InterfaceServices\RecommendationServiceInterface;
use App\Weather\DTOs\Requests\WeatherQuery;
use App\Weather\Exceptions\WeatherUnavailable;
use App\Weather\InterfaceServices\WeatherServiceInterface;

class RecommendationService implements RecommendationServiceInterface
{
    private const REQUIRED_KEYS = ['temperature_2m', 'relative_humidity_2m', 'wind_speed_10m', 'weather_code'];

    public function __construct(
        private readonly WeatherServiceInterface $weather,
        private readonly GeminiServiceInterface $gemini,
    ) {}

    public function forCurrentWeather(RecommendationQuery $query): RecommendationResponse
    {
        try {
            $current = $this->weather->current(new WeatherQuery($query->latitude, $query->longitude))->current;
        } catch (WeatherUnavailable $e) {
            report($e);

            throw RecommendationUnavailable::weather();
        }

        if ($current === null) {
            throw RecommendationUnavailable::weather();
        }

        foreach (self::REQUIRED_KEYS as $key) {
            if (! isset($current[$key])) {
                throw RecommendationUnavailable::weather();
            }
        }

        $description = $this->describe((int) $current['weather_code']);

        $prompt = 'You are a movie recommendation expert. '
            ."The current weather is: {$current['temperature_2m']}°C, {$description}, "
            ."humidity {$current['relative_humidity_2m']}%, wind {$current['wind_speed_10m']} km/h. "
            .'Recommend exactly 10 good movies that fit this weather and mood. '
            .'Return ONLY valid JSON, no markdown, no extra text, in this format: '
            .'{"movies":[{"title":"","year":0,"genre":"","reason":""}]}';

        try {
            return new RecommendationResponse($this->gemini->ask($prompt));
        } catch (GeminiUnavailable $e) {
            report($e);

            throw RecommendationUnavailable::generation();
        }
    }

    private function describe(int $code): string
    {
        return match (true) {
            $code === 0 => 'clear sky',
            $code <= 3 => 'partly cloudy',
            $code <= 48 => 'foggy',
            $code <= 67 => 'rainy',
            $code <= 77 => 'snowy',
            $code <= 82 => 'rain showers',
            default => 'stormy',
        };
    }
}
