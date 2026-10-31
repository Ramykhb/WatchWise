<?php

namespace Tests\Unit\MovieRecommendation;

use App\Groq\Exceptions\GroqUnavailable;
use App\Groq\InterfaceServices\GroqStructuredOutputInterface;
use App\Movie\DTOs\Responses\MoviePageResponse;
use App\Movie\DTOs\Responses\MovieResponse;
use App\Movie\InterfaceServices\MovieServiceInterface;
use App\MovieRecommendation\DTOs\Requests\RecommendationQuery;
use App\MovieRecommendation\Exceptions\RecommendationUnavailable;
use App\MovieRecommendation\Services\RecommendationService;
use App\Watchlist\InterfaceRepository\WatchlistRepositoryInterface;
use App\Weather\DTOs\Requests\WeatherQuery;
use App\Weather\DTOs\Responses\WeatherResponse;
use App\Weather\Exceptions\WeatherUnavailable;
use App\Weather\InterfaceServices\WeatherServiceInterface;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecommendationServiceTest extends TestCase
{
    private MockInterface $weather;

    private MockInterface $groq;

    private MockInterface $movies;

    private RecommendationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->weather = Mockery::mock(WeatherServiceInterface::class);
        $this->groq = Mockery::mock(GroqStructuredOutputInterface::class);
        $this->movies = Mockery::mock(MovieServiceInterface::class);
        $this->service = new RecommendationService($this->weather, $this->movies, Mockery::mock(WatchlistRepositoryInterface::class), $this->groq);
    }

    /**
     * @param  array<string, mixed>|null  $current
     */
    private function weatherReturns(?array $current): void
    {
        $this->weather->shouldReceive('current')->once()->andReturn(new WeatherResponse(1.0, 2.0, false, $current));
    }

    /**
     * @return array<string, mixed>
     */
    private function current(int $code): array
    {
        return [
            'temperature_2m' => 12,
            'relative_humidity_2m' => 80,
            'wind_speed_10m' => 15,
            'weather_code' => $code,
        ];
    }

    private function candidates(): MoviePageResponse
    {
        return new MoviePageResponse(1, 1, 1, [new MovieResponse(42, 'Movie 42', 'Overview', '2020-01-01', 2020, 4.0, 10, [18], null, null)]);
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function weatherCodes(): array
    {
        return [
            'clear' => [0, 'clear sky'],
            'partly cloudy' => [2, 'partly cloudy'],
            'foggy' => [45, 'foggy'],
            'rainy' => [61, 'rainy'],
            'snowy' => [71, 'snowy'],
            'showers' => [80, 'rain showers'],
            'stormy' => [95, 'stormy'],
        ];
    }

    #[DataProvider('weatherCodes')]
    public function test_it_describes_the_weather_code_in_the_prompt(int $code, string $description): void
    {
        $this->weatherReturns($this->current($code));
        $this->movies->shouldReceive('discover')->once()->andReturn($this->candidates());
        $this->groq->shouldReceive('askWithSchema')
            ->once()
            ->withArgs(fn (string $prompt, string $name, array $schema, int $maxCompletionTokens, string $keyProfile) => str_contains($prompt, "12°C, {$description}, humidity 80%, wind 15 km/h.")
                && str_contains($prompt, 'Choose up to 10 movies')
                && $name === 'weather_movie_recommendations' && $keyProfile === 'default'
                && $schema['properties']['movies']['items']['properties']['id']['type'] === 'integer')
            ->andReturn(['movies' => [['id' => 42, 'reason' => 'Fits']]]);

        $result = $this->service->forCurrentWeather(new RecommendationQuery);

        $this->assertSame(42, $result->payload['movies'][0]['id']);
    }

    public function test_it_passes_the_coordinates_to_the_weather_service(): void
    {
        $this->weather->shouldReceive('current')
            ->once()
            ->withArgs(fn (WeatherQuery $q) => $q->latitude === 33.5 && $q->longitude === 35.5)
            ->andReturn(new WeatherResponse(33.5, 35.5, false, $this->current(0)));
        $this->movies->shouldReceive('discover')->once()->andReturn($this->candidates());
        $this->groq->shouldReceive('askWithSchema')->once()->andReturn(['movies' => []]);

        $this->service->forCurrentWeather(new RecommendationQuery(33.5, 35.5));
    }

    public function test_it_maps_a_weather_failure(): void
    {
        $this->weather->shouldReceive('current')->once()->andThrow(new WeatherUnavailable);
        $this->groq->shouldNotReceive('askWithSchema');

        $this->expectException(RecommendationUnavailable::class);
        $this->expectExceptionMessage('Could not get the weather');

        $this->service->forCurrentWeather(new RecommendationQuery);
    }

    public function test_it_maps_missing_current_weather(): void
    {
        $this->weatherReturns(null);
        $this->groq->shouldNotReceive('askWithSchema');

        $this->expectException(RecommendationUnavailable::class);
        $this->expectExceptionMessage('Could not get the weather');

        $this->service->forCurrentWeather(new RecommendationQuery);
    }

    public function test_it_maps_incomplete_current_weather(): void
    {
        $this->weatherReturns(['temperature_2m' => 12]);
        $this->groq->shouldNotReceive('askWithSchema');

        $this->expectException(RecommendationUnavailable::class);
        $this->expectExceptionMessage('Could not get the weather');

        $this->service->forCurrentWeather(new RecommendationQuery);
    }

    public function test_it_maps_a_groq_failure(): void
    {
        $this->weatherReturns($this->current(61));
        $this->movies->shouldReceive('discover')->once()->andReturn($this->candidates());
        $this->groq->shouldReceive('askWithSchema')->once()->andThrow(new GroqUnavailable('HTTP 500'));

        $this->expectException(RecommendationUnavailable::class);
        $this->expectExceptionMessage('Could not generate recommendations');

        $this->service->forCurrentWeather(new RecommendationQuery);
    }
}
