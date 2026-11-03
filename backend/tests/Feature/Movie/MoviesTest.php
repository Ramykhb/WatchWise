<?php

namespace Tests\Feature\Movie;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
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

    public function test_upcoming_returns_films_first_released_after_today_most_anticipated_first(): void
    {
        config(['services.tmdb.key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake([
            'https://api.themoviedb.org/3/discover/movie*' => Http::response([
                'page' => 2,
                'total_pages' => 9,
                'total_results' => 170,
                'results' => [[
                    'id' => 1084242,
                    'title' => 'Dune: Part Three',
                    'overview' => 'The saga concludes.',
                    'release_date' => '2026-12-15',
                    'vote_average' => 0,
                    'vote_count' => 0,
                    'genre_ids' => [878, 12],
                    'poster_path' => '/dune3.jpg',
                    'backdrop_path' => null,
                ]],
            ]),
        ]);
        $this->travelTo('2026-11-03 23:30:00');

        $this->getJson('/api/movies?list=upcoming&page=2')
            ->assertOk()
            ->assertJsonPath('list', 'upcoming')
            ->assertJsonPath('movies.0.title', 'Dune: Part Three')
            ->assertJsonPath('movies.0.release_date', '2026-12-15');

        Http::assertSent(fn ($request) => $request['primary_release_date.gte'] === '2026-11-04'
            && $request['sort_by'] === 'popularity.desc'
            && $request['page'] === 2
            && $request['include_adult'] === 'false'
            && $request['api_key'] === 'test-key');
    }

    public function test_each_list_page_is_cached_for_an_hour(): void
    {
        config(['services.tmdb.key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake([
            'https://api.themoviedb.org/3/discover/movie*' => Http::response(['page' => 1, 'total_pages' => 2, 'total_results' => 21, 'results' => [['id' => 1, 'title' => 'Upcoming film']]]),
            'https://api.themoviedb.org/3/movie/popular*' => Http::response(['page' => 1, 'total_pages' => 1, 'total_results' => 1, 'results' => [['id' => 2, 'title' => 'Popular film']]]),
        ]);

        $first = $this->getJson('/api/movies?list=upcoming')->assertOk()->json();
        $this->getJson('/api/movies?list=upcoming')->assertOk()->assertExactJson($first);
        Http::assertSentCount(1);

        $this->getJson('/api/movies?list=upcoming&page=2')->assertOk();
        $this->getJson('/api/movies?list=popular')->assertOk()->assertJsonPath('movies.0.title', 'Popular film');
        Http::assertSentCount(3);

        $this->travel(61)->minutes();
        $this->getJson('/api/movies?list=upcoming')->assertOk();
        Http::assertSentCount(4);
    }

    public function test_upcoming_retries_a_connection_timeout(): void
    {
        config(['services.tmdb.key' => 'test-key']);
        Http::preventStrayRequests();
        Sleep::fake();
        $attempts = 0;
        Http::fake([
            'https://api.themoviedb.org/3/discover/movie*' => function () use (&$attempts) {
                if (++$attempts === 1) {
                    throw new ConnectionException('Temporary connection timeout');
                }

                return Http::response(['page' => 1, 'total_pages' => 1, 'total_results' => 1, 'results' => [['id' => 1, 'title' => 'Dune: Part Three']]]);
            },
        ]);

        $this->getJson('/api/movies?list=upcoming')
            ->assertOk()
            ->assertJsonPath('movies.0.title', 'Dune: Part Three');

        $this->assertSame(2, $attempts);
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
