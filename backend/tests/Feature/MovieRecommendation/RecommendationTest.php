<?php

namespace Tests\Feature\MovieRecommendation;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecommendationTest extends TestCase
{
    private const GEMINI_URL = 'https://generativelanguage.googleapis.com/*';

    private const METEO_URL = 'https://api.open-meteo.com/*';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'test-key', 'services.gemini.model' => 'm']);
    }

    /**
     * @return array<string, mixed>
     */
    private function weatherBody(): array
    {
        return [
            'current' => [
                'temperature_2m' => 12,
                'relative_humidity_2m' => 80,
                'wind_speed_10m' => 15,
                'weather_code' => 61,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function geminiBody(): array
    {
        $fakeMovies = array_map(fn ($i) => [
            'title' => "Movie $i",
            'year' => 2000 + $i,
            'genre' => 'Drama',
            'reason' => 'Fits the weather',
        ], range(1, 10));

        return [
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'text' => json_encode(['movies' => $fakeMovies]),
                    ]],
                ],
            ]],
        ];
    }

    public function test_it_asks_gemini_for_10_movies_based_on_the_weather(): void
    {
        Http::fake([
            self::METEO_URL => Http::response($this->weatherBody()),
            self::GEMINI_URL => Http::response($this->geminiBody()),
        ]);

        $response = $this->getJson('/api/movie-recommendations');

        $response
            ->assertOk()
            ->assertJsonCount(10, 'movies')
            ->assertJsonPath('movies.0.title', 'Movie 1');

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), 'generativelanguage.googleapis.com')) {
                return false;
            }

            $prompt = $request['contents'][0]['parts'][0]['text'];

            return str_contains($prompt, '12')
                && str_contains($prompt, '10')
                && $request['generationConfig']['responseMimeType'] === 'application/json';
        });
    }

    public function test_it_rejects_a_single_coordinate(): void
    {
        Http::fake();

        $this->getJson('/api/movie-recommendations?latitude=33.8')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('longitude');

        $this->getJson('/api/movie-recommendations?longitude=35.5')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('latitude');

        Http::assertNothingSent();
    }

    public function test_it_returns_a_502_when_the_weather_fails(): void
    {
        Http::fake([
            self::METEO_URL => Http::response('boom', 500),
            self::GEMINI_URL => Http::response($this->geminiBody()),
        ]);

        $this->getJson('/api/movie-recommendations')
            ->assertStatus(502)
            ->assertExactJson(['error' => 'Could not get the weather']);
    }

    public function test_it_returns_a_502_without_leaking_the_gemini_error(): void
    {
        Http::fake([
            self::METEO_URL => Http::response($this->weatherBody()),
            self::GEMINI_URL => Http::response(['error' => ['message' => 'SECRET-GEMINI-MARKER']], 500),
        ]);

        $response = $this->getJson('/api/movie-recommendations');

        $response
            ->assertStatus(502)
            ->assertExactJson(['error' => 'Could not generate recommendations']);

        $this->assertStringNotContainsString('SECRET-GEMINI-MARKER', $response->getContent());
    }

    public function test_it_throttles_the_eleventh_request_in_a_minute(): void
    {
        Http::fake([
            self::METEO_URL => Http::response($this->weatherBody()),
            self::GEMINI_URL => Http::response($this->geminiBody()),
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/movie-recommendations')->assertOk();
        }

        $this->getJson('/api/movie-recommendations')->assertTooManyRequests();
    }
}
