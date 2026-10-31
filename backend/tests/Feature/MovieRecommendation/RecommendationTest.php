<?php

namespace Tests\Feature\MovieRecommendation;

use App\Gemini\Exceptions\GeminiUnavailable;
use App\Gemini\InterfaceServices\GeminiServiceInterface;
use App\Movie\DTOs\Responses\MoviePageResponse;
use App\Movie\DTOs\Responses\MovieResponse;
use App\Movie\InterfaceServices\MovieServiceInterface;
use App\Weather\DTOs\Responses\WeatherResponse;
use App\Weather\Exceptions\WeatherUnavailable;
use App\Weather\InterfaceServices\WeatherServiceInterface;
use Tests\TestCase;

class RecommendationTest extends TestCase
{
    private function mockDependencies(): array
    {
        $weather = $this->mock(WeatherServiceInterface::class);
        $weather->shouldReceive('current')->andReturn(new WeatherResponse(1, 2, false, [
            'temperature_2m' => 12, 'relative_humidity_2m' => 80, 'wind_speed_10m' => 15, 'weather_code' => 61,
        ]));

        $movie = new MovieResponse(42, 'Movie 42', 'Overview', '2020-01-01', 2020, 4.0, 10, [18], null, null);
        $movies = $this->mock(MovieServiceInterface::class);
        $movies->shouldReceive('discover')->andReturn(new MoviePageResponse(1, 1, 1, [$movie]));
        $gemini = $this->mock(GeminiServiceInterface::class);

        return [$gemini, $movies];
    }

    public function test_it_returns_gemini_selected_tmdb_movies(): void
    {
        [$gemini] = $this->mockDependencies();
        $gemini->shouldReceive('ask')->once()->andReturn(['movies' => [['id' => 42, 'reason' => 'Fits the weather']]]);

        $this->getJson('/api/movie-recommendations')
            ->assertOk()
            ->assertJsonCount(1, 'movies')
            ->assertJsonPath('movies.0.id', 42)
            ->assertJsonPath('movies.0.reason', 'Fits the weather');
    }

    public function test_it_rejects_a_single_coordinate(): void
    {
        [$gemini] = $this->mockDependencies();

        $this->getJson('/api/movie-recommendations?latitude=33.8')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('longitude');

        $this->getJson('/api/movie-recommendations?longitude=35.5')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('latitude');

        $gemini->shouldNotReceive('ask');
    }

    public function test_it_returns_a_502_when_the_weather_fails(): void
    {
        $gemini = $this->mock(GeminiServiceInterface::class);
        $this->mock(MovieServiceInterface::class);
        $weather = $this->mock(WeatherServiceInterface::class);
        $weather->shouldReceive('current')->andThrow(new WeatherUnavailable('upstream error'));
        $this->getJson('/api/movie-recommendations')
            ->assertStatus(502)
            ->assertExactJson(['error' => 'Could not get the weather']);

        $gemini->shouldNotReceive('ask');
    }

    public function test_it_returns_a_502_without_leaking_the_gemini_error(): void
    {
        [$gemini] = $this->mockDependencies();
        $gemini->shouldReceive('ask')->once()->andThrow(new GeminiUnavailable('SECRET-GEMINI-MARKER'));

        $response = $this->getJson('/api/movie-recommendations');

        $response->assertStatus(502)->assertExactJson(['error' => 'Could not generate recommendations']);
        $this->assertStringNotContainsString('SECRET-GEMINI-MARKER', $response->getContent());
    }

    public function test_it_throttles_the_eleventh_request_in_a_minute(): void
    {
        [$gemini] = $this->mockDependencies();
        $gemini->shouldReceive('ask')->times(10)->andReturn(['movies' => []]);

        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/movie-recommendations')->assertOk();
        }

        $this->getJson('/api/movie-recommendations')->assertTooManyRequests();
    }
}
