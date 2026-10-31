<?php

namespace Tests\Feature\MovieRecommendation;

use App\Groq\Exceptions\GroqUnavailable;
use App\Groq\InterfaceServices\GroqStructuredOutputInterface;
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
        $groq = $this->mock(GroqStructuredOutputInterface::class);

        return [$groq, $movies];
    }

    public function test_it_returns_groq_selected_tmdb_movies(): void
    {
        [$groq] = $this->mockDependencies();
        $groq->shouldReceive('askWithSchema')->once()->andReturn(['movies' => [['id' => 42, 'reason' => 'Fits the weather']]]);

        $this->getJson('/api/movie-recommendations')
            ->assertOk()
            ->assertJsonCount(1, 'movies')
            ->assertJsonPath('movies.0.id', 42)
            ->assertJsonPath('movies.0.reason', 'Fits the weather');
    }

    public function test_it_rejects_a_single_coordinate(): void
    {
        [$groq] = $this->mockDependencies();

        $this->getJson('/api/movie-recommendations?latitude=33.8')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('longitude');

        $this->getJson('/api/movie-recommendations?longitude=35.5')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('latitude');

        $groq->shouldNotReceive('askWithSchema');
    }

    public function test_it_returns_a_502_when_the_weather_fails(): void
    {
        $groq = $this->mock(GroqStructuredOutputInterface::class);
        $this->mock(MovieServiceInterface::class);
        $weather = $this->mock(WeatherServiceInterface::class);
        $weather->shouldReceive('current')->andThrow(new WeatherUnavailable('upstream error'));
        $this->getJson('/api/movie-recommendations')
            ->assertStatus(502)
            ->assertExactJson(['error' => 'Could not get the weather']);

        $groq->shouldNotReceive('askWithSchema');
    }

    public function test_it_returns_a_502_without_leaking_the_groq_error(): void
    {
        [$groq] = $this->mockDependencies();
        $groq->shouldReceive('askWithSchema')->once()->andThrow(new GroqUnavailable('SECRET-GROQ-MARKER'));

        $response = $this->getJson('/api/movie-recommendations');

        $response->assertStatus(502)->assertExactJson(['error' => 'Could not generate recommendations']);
        $this->assertStringNotContainsString('SECRET-GROQ-MARKER', $response->getContent());
    }

    public function test_it_throttles_the_eleventh_request_in_a_minute(): void
    {
        [$groq] = $this->mockDependencies();
        $groq->shouldReceive('askWithSchema')->times(10)->andReturn(['movies' => []]);

        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/movie-recommendations')->assertOk();
        }

        $this->getJson('/api/movie-recommendations')->assertTooManyRequests();
    }
}
