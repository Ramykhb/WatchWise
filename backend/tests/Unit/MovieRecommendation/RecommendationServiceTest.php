<?php

namespace Tests\Unit\MovieRecommendation;

use App\Gemini\Exceptions\GeminiUnavailable;
use App\Gemini\InterfaceServices\GeminiServiceInterface;
use App\MovieRecommendation\DTOs\Requests\RecommendationQuery;
use App\MovieRecommendation\Exceptions\RecommendationUnavailable;
use App\MovieRecommendation\Services\RecommendationService;
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

    private MockInterface $gemini;

    private RecommendationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->weather = Mockery::mock(WeatherServiceInterface::class);
        $this->gemini = Mockery::mock(GeminiServiceInterface::class);
        $this->service = new RecommendationService($this->weather, $this->gemini);
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
        $this->gemini->shouldReceive('ask')
            ->once()
            ->withArgs(fn (string $prompt) => str_contains($prompt, "12°C, {$description}, humidity 80%, wind 15 km/h.")
                && str_contains($prompt, 'Recommend exactly 10 good movies')
                && str_contains($prompt, 'Return ONLY valid JSON'))
            ->andReturn(['movies' => []]);

        $result = $this->service->forCurrentWeather(new RecommendationQuery);

        $this->assertSame(['movies' => []], $result->payload);
    }

    public function test_it_passes_the_coordinates_to_the_weather_service(): void
    {
        $this->weather->shouldReceive('current')
            ->once()
            ->withArgs(fn (WeatherQuery $q) => $q->latitude === 33.5 && $q->longitude === 35.5)
            ->andReturn(new WeatherResponse(33.5, 35.5, false, $this->current(0)));
        $this->gemini->shouldReceive('ask')->once()->andReturn(['movies' => []]);

        $this->service->forCurrentWeather(new RecommendationQuery(33.5, 35.5));
    }

    public function test_it_maps_a_weather_failure(): void
    {
        $this->weather->shouldReceive('current')->once()->andThrow(new WeatherUnavailable);
        $this->gemini->shouldNotReceive('ask');

        $this->expectException(RecommendationUnavailable::class);
        $this->expectExceptionMessage('Could not get the weather');

        $this->service->forCurrentWeather(new RecommendationQuery);
    }

    public function test_it_maps_missing_current_weather(): void
    {
        $this->weatherReturns(null);
        $this->gemini->shouldNotReceive('ask');

        $this->expectException(RecommendationUnavailable::class);
        $this->expectExceptionMessage('Could not get the weather');

        $this->service->forCurrentWeather(new RecommendationQuery);
    }

    public function test_it_maps_incomplete_current_weather(): void
    {
        $this->weatherReturns(['temperature_2m' => 12]);
        $this->gemini->shouldNotReceive('ask');

        $this->expectException(RecommendationUnavailable::class);
        $this->expectExceptionMessage('Could not get the weather');

        $this->service->forCurrentWeather(new RecommendationQuery);
    }

    public function test_it_maps_a_gemini_failure(): void
    {
        $this->weatherReturns($this->current(61));
        $this->gemini->shouldReceive('ask')->once()->andThrow(new GeminiUnavailable('HTTP 500'));

        $this->expectException(RecommendationUnavailable::class);
        $this->expectExceptionMessage('Could not generate recommendations');

        $this->service->forCurrentWeather(new RecommendationQuery);
    }
}
