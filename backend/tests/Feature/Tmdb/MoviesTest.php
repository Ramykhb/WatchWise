<?php

namespace Tests\Feature\Tmdb;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MoviesTest extends TestCase
{
    public function test_movies_returns_popular_movies_by_default(): void
    {
        config(['services.tmdb.key' => 'test-key']);

        Http::fake([
            'https://api.themoviedb.org/*' => Http::response([
                'page' => 1,
                'total_pages' => 3,
                'total_results' => 60,
                'results' => [[
                    'id' => 81920,
                    'title' => 'Neon Tide',
                    'overview' => 'A glowing current.',
                    'release_date' => '2026-03-06',
                    'vote_average' => 9.6,
                    'vote_count' => 1200,
                    'genre_ids' => [878, 12],
                    'poster_path' => '/neon.jpg',
                    'backdrop_path' => null,
                ]],
            ]),
        ]);

        $this->getJson('/api/movies')
            ->assertOk()
            ->assertJsonPath('list', 'popular')
            ->assertJsonPath('movies.0.title', 'Neon Tide')
            ->assertJsonPath('movies.0.year', 2026)
            ->assertJsonPath('movies.0.rating', 4.8)
            ->assertJsonPath('movies.0.poster_url', 'https://image.tmdb.org/t/p/w500/neon.jpg')
            ->assertJsonPath('movies.0.backdrop_url', null);

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.themoviedb.org/3/movie/popular?')
            && $request['api_key'] === 'test-key'
            && $request['page'] === 1);
    }

    public function test_movies_rejects_an_unknown_list(): void
    {
        $this->getJson('/api/movies?list=random')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['list']);
    }

    public function test_movies_returns_502_when_tmdb_fails(): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response([], 500)]);

        $this->getJson('/api/movies')
            ->assertStatus(502)
            ->assertJsonPath('message', 'Movie service is temporarily unavailable.');
    }
}
