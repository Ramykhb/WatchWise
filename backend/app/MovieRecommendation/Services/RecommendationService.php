<?php

namespace App\MovieRecommendation\Services;

use App\Groq\Exceptions\GroqUnavailable;
use App\Groq\InterfaceServices\GroqStructuredOutputInterface;
use App\Movie\DTOs\Requests\MovieDiscoverQuery;
use App\Movie\InterfaceServices\MovieServiceInterface;
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
        private readonly GroqStructuredOutputInterface $groq,
        private readonly MovieServiceInterface $movies,
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

        $candidates = $this->movies->discover(new MovieDiscoverQuery(
            // TMDB's pipe separator means match any of these genres; commas would require all of them.
            genres: implode('|', $this->weatherGenres((int) $current['weather_code'])),
            sort: 'popularity',
            page: 1,
        ))->movies;

        if ($candidates === []) {
            return new RecommendationResponse(['movies' => []]);
        }

        $candidateData = array_map(fn ($movie): array => $movie->jsonSerialize(), $candidates);

        $prompt = 'You are a movie recommendation expert. '
            ."The current weather is: {$current['temperature_2m']}°C, {$description}, "
            ."humidity {$current['relative_humidity_2m']}%, wind {$current['wind_speed_10m']} km/h. "
            .'Choose up to 10 movies from the supplied TMDB candidates that best fit this weather. '
            .'Only select candidate IDs; do not invent or alter movies. '
            .'Return only the required JSON object. '
            .'TMDB candidates: '.json_encode($candidateData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            $selection = $this->groq->askWithSchema($prompt, 'weather_movie_recommendations', [
                'type' => 'object',
                'properties' => [
                    'movies' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'reason' => ['type' => 'string'],
                            ],
                            'required' => ['id', 'reason'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required' => ['movies'],
                'additionalProperties' => false,
            ]);
        } catch (GroqUnavailable $e) {
            report($e);

            throw RecommendationUnavailable::generation();
        }

        $byId = collect($candidates)->keyBy('id');
        $recommendations = collect($selection['movies'] ?? [])
            ->filter(fn ($item): bool => is_array($item) && isset($item['id']) && is_numeric($item['id']))
            ->unique(fn (array $item): int => (int) $item['id'])
            ->filter(fn (array $item): bool => $byId->has((int) $item['id']))
            ->take(10)
            ->map(function (array $item) use ($byId): array {
                return [
                    ...$byId->get((int) $item['id'])->jsonSerialize(),
                    'reason' => is_string($item['reason'] ?? null) ? $item['reason'] : '',
                ];
            })
            ->values()
            ->all();

        return new RecommendationResponse(['movies' => $recommendations]);
    }

    /** @return list<int> TMDB movie genre IDs suited to the current weather */
    private function weatherGenres(int $code): array
    {
        return match (true) {
            $code === 0 || $code <= 3 => [35, 12, 16, 10751], // comedy, adventure, animation, family
            $code <= 48 => [9648, 18, 14], // mystery, drama, fantasy
            $code <= 67 => [18, 9648, 53, 10749], // drama, mystery, thriller, romance
            $code <= 77 => [10751, 14, 12, 16], // family, fantasy, adventure, animation
            $code <= 82 => [18, 35, 12, 10749], // drama, comedy, adventure, romance
            default => [53, 27, 28, 9648], // thriller, horror, action, mystery
        };
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
