<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiWeatherRecommendation extends TestCase
{
    public function test_it_asks_gemini_for_10_movies_based_on_the_weather(): void
    {
        config(['services.gemini.key' => 'test-key']);

        // Fake Gemini answer: 10 movies, so your code has something to parse
        $fakeMovies = array_map(fn ($i) => [
            'title' => "Movie $i",
            'year' => 2000 + $i,
            'genre' => 'Drama',
            'reason' => 'Fits the weather',
        ], range(1, 10));

        Http::fake([
            'https://api.open-meteo.com/*' => Http::response([
                'current' => [
                    'temperature_2m' => 12,
                    'relative_humidity_2m' => 80,
                    'wind_speed_10m' => 15,
                    'weather_code' => 61,
                ],
            ]),
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode(['movies' => $fakeMovies]),
                        ]],
                    ],
                ]],
            ]),
        ]);

        $response = $this->getJson('/api/movie-recommendations');

        $response
            ->assertOk()
            ->assertJsonCount(10, 'movies')
            ->assertJsonPath('movies.0.title', 'Movie 1');

        // Check what we actually SENT to Gemini (the prompt)
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'generativelanguage.googleapis.com')) {
                return false;
            }

            $prompt = $request['contents'][0]['parts'][0]['text'];

            return str_contains($prompt, '12')
                && str_contains($prompt, '10')
                && $request['generationConfig']['responseMimeType'] === 'application/json';
        });
    }
}