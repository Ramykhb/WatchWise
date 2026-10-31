<?php

namespace Tests\Feature\Movie;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TrendingTest extends TestCase
{
    public static function mediaTypes(): array
    {
        return ['movies' => ['movie'], 'TV shows' => ['tv']];
    }

    private function page(string $type): array
    {
        return [
            'page' => 1, 'total_pages' => 1, 'total_results' => 12,
            'results' => array_map(fn (int $rank): array => [
                'id' => 100 - $rank,
                $type === 'tv' ? 'name' : 'title' => 'Rank '.$rank,
                $type === 'tv' ? 'first_air_date' : 'release_date' => '2026-01-01',
                'vote_average' => 8, 'vote_count' => 100, 'genre_ids' => [18],
                'poster_path' => '/poster.jpg',
            ], range(1, 12)),
        ];
    }

    #[DataProvider('mediaTypes')]
    public function test_it_returns_weekly_trending_titles_in_tmdb_rank_order_and_caches_them(string $type): void
    {
        config(['services.tmdb.key' => 'test-key']);
        Http::fake(['api.themoviedb.org/*' => Http::response($this->page($type))]);

        $first = $this->getJson('/api/movies/trending?type='.$type)->assertOk()
            ->assertJsonPath('time_window', 'week')
            ->assertJsonPath('movies.0.id', 99)->assertJsonPath('movies.0.title', 'Rank 1')
            ->assertJsonPath('movies.9.title', 'Rank 10')
            ->assertJsonPath('movies.0.media_type', $type)
            ->assertJsonPath('movies.0.year', 2026)->json();
        $this->getJson('/api/movies/trending?type='.$type)->assertOk()->assertExactJson($first);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/trending/'.$type.'/week')
            && $request['api_key'] === 'test-key');
        $this->travel(61)->minutes();
        $this->getJson('/api/movies/trending?type='.$type)->assertOk();
        Http::assertSentCount(2);
    }

    public function test_movie_and_tv_caches_are_separate(): void
    {
        config(['services.tmdb.key' => 'test-key']);
        Http::fake(fn (Request $request) => Http::response($this->page(str_contains($request->url(), '/tv/') ? 'tv' : 'movie')));

        $this->getJson('/api/movies/trending')->assertOk()->assertJsonPath('movies.0.media_type', 'movie');
        $this->getJson('/api/movies/trending?type=tv')->assertOk()->assertJsonPath('movies.0.media_type', 'tv');
        Http::assertSentCount(2);
    }

    public function test_it_rejects_unsupported_media_types(): void
    {
        Http::preventStrayRequests();
        $this->getJson('/api/movies/trending?type=person')->assertUnprocessable()->assertJsonValidationErrors('type');
        Http::assertNothingSent();
    }

    public function test_it_retries_a_connection_timeout_and_caches_the_successful_result(): void
    {
        config(['services.tmdb.key' => 'test-key']);
        Sleep::fake();
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            if (++$attempts === 1) {
                throw new ConnectionException('Temporary connection timeout');
            }

            return Http::response($this->page('movie'));
        });

        $this->getJson('/api/movies/trending')->assertOk()->assertJsonPath('movies.0.title', 'Rank 1');
        $this->getJson('/api/movies/trending')->assertOk();
        $this->assertSame(2, $attempts);
    }

    public function test_exhausted_connection_retries_do_not_cache_the_failure(): void
    {
        config(['services.tmdb.key' => 'test-key']);
        Sleep::fake();
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            if (++$attempts <= 2) {
                throw new ConnectionException('Persistent connection timeout');
            }

            return Http::response($this->page('movie'));
        });

        $this->getJson('/api/movies/trending')->assertStatus(502);
        $this->getJson('/api/movies/trending')->assertOk()->assertJsonPath('movies.0.title', 'Rank 1');
        $this->assertSame(3, $attempts);
    }

    public function test_a_tmdb_failure_is_not_cached(): void
    {
        config(['services.tmdb.key' => 'test-key']);
        Http::fakeSequence()->push([], 500)->push($this->page('movie'));

        $this->getJson('/api/movies/trending')->assertStatus(502);
        $this->getJson('/api/movies/trending')->assertOk();
        Http::assertSentCount(2);
    }
}
