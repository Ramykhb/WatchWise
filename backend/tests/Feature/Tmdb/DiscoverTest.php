<?php

namespace Tests\Feature\Tmdb;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscoverTest extends TestCase
{
    /**
     * A one-movie TMDB page to answer every faked request with.
     *
     * @return array<string, mixed>
     */
    private function tmdbPage(): array
    {
        return [
            'page' => 1,
            'total_pages' => 900,
            'total_results' => 18000,
            'results' => [['id' => 81920, 'title' => 'Neon Tide', 'release_date' => '2026-03-06', 'vote_average' => 8.4]],
        ];
    }

    public function test_genres_are_returned_and_cached(): void
    {
        Http::fake(['https://api.themoviedb.org/3/genre/movie/list*' => Http::response([
            'genres' => [['id' => 878, 'name' => 'Science Fiction']],
        ])]);

        $this->getJson('/api/genres')->assertOk()->assertJsonPath('genres', [['id' => 878, 'name' => 'Science Fiction']]);
        $this->getJson('/api/genres')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_genres_return_502_when_tmdb_fails(): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response([], 500)]);

        $this->getJson('/api/genres')
            ->assertStatus(502)
            ->assertJsonPath('message', 'Movie service is temporarily unavailable.');
    }

    public function test_search_sends_the_query_and_caps_total_pages(): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response($this->tmdbPage())]);

        $this->getJson('/api/movies/search?q=neon&page=2')
            ->assertOk()
            ->assertJsonPath('query', 'neon')
            ->assertJsonPath('total_pages', 500)
            ->assertJsonPath('movies.0.title', 'Neon Tide');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/search/movie')
            && $request['query'] === 'neon'
            && $request['page'] === 2);
    }

    public function test_discover_merges_movies_and_tv_shows_by_the_chosen_sort(): void
    {
        Http::fake([
            'https://api.themoviedb.org/3/discover/movie*' => Http::response([
                'page' => 1, 'total_pages' => 3, 'total_results' => 50,
                'results' => [['id' => 1, 'title' => 'Zed', 'release_date' => '2020-01-01', 'popularity' => 10]],
            ]),
            'https://api.themoviedb.org/3/discover/tv*' => Http::response([
                'page' => 1, 'total_pages' => 5, 'total_results' => 70,
                'results' => [['id' => 2, 'name' => 'Alpha', 'first_air_date' => '2022-05-05', 'popularity' => 99]],
            ]),
        ]);

        $this->getJson('/api/movies/discover?type=all&genres=878&tv_genres=10765')
            ->assertOk()
            ->assertJsonPath('total_results', 120)
            ->assertJsonPath('total_pages', 5)
            ->assertJsonPath('movies.0.title', 'Alpha')
            ->assertJsonPath('movies.0.media_type', 'tv')
            ->assertJsonPath('movies.0.release_date', '2022-05-05')
            ->assertJsonPath('movies.1.media_type', 'movie');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/discover/tv') && $request['with_genres'] === '10765');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/discover/movie') && $request['with_genres'] === '878');
    }

    public function test_tv_details_use_the_tv_endpoint(): void
    {
        Http::fake(['https://api.themoviedb.org/3/tv/5*' => Http::response([
            'id' => 5, 'name' => 'Show', 'first_air_date' => '2019-02-02', 'episode_run_time' => [45],
            'created_by' => [['id' => 9, 'name' => 'Maker']],
            'content_ratings' => ['results' => [['iso_3166_1' => 'US', 'rating' => 'TV-MA']]],
        ])]);

        $this->getJson('/api/movies/5?type=tv')
            ->assertOk()
            ->assertJsonPath('title', 'Show')
            ->assertJsonPath('media_type', 'tv')
            ->assertJsonPath('runtime', 45)
            ->assertJsonPath('certification', 'TV-MA')
            ->assertJsonPath('directors.0.name', 'Maker');
    }

    public function test_search_needs_a_query(): void
    {
        $this->getJson('/api/movies/search')->assertUnprocessable()->assertJsonValidationErrors(['q']);
    }

    public function test_discover_turns_the_filters_into_tmdb_parameters(): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response($this->tmdbPage())]);

        $this->getJson('/api/movies/discover?genres=878,12&year_from=2015&year_to=2026&min_rating=4&runtime_min=90&runtime_max=120&language=en&sort=rating')
            ->assertOk()
            ->assertJsonPath('sort', 'rating')
            ->assertJsonPath('movies.0.rating', 4.2);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/discover/movie')
            && $request['with_genres'] === '878,12'
            && $request['primary_release_date.gte'] === '2015-01-01'
            && $request['primary_release_date.lte'] === '2026-12-31'
            && $request['vote_average.gte'] == 8
            && $request['with_runtime.gte'] === 90
            && $request['with_runtime.lte'] === 120
            && $request['with_original_language'] === 'en'
            && $request['sort_by'] === 'vote_average.desc'
            && $request['vote_count.gte'] === 100);
    }

    public function test_discover_defaults_to_popularity_without_filters(): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response($this->tmdbPage())]);

        $this->getJson('/api/movies/discover')->assertOk()->assertJsonPath('sort', 'popularity');

        Http::assertSent(fn ($request) => $request['sort_by'] === 'popularity.desc'
            && ! isset($request['with_genres'])
            && ! isset($request['vote_count.gte']));
    }

    public function test_discover_accepts_an_upper_bound_on_its_own(): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response($this->tmdbPage())]);

        $this->getJson('/api/movies/discover?runtime_max=89&year_to=2020')->assertOk();

        Http::assertSent(fn ($request) => $request['with_runtime.lte'] === 89
            && $request['primary_release_date.lte'] === '2020-12-31'
            && ! isset($request['with_runtime.gte']));
    }

    public function test_discover_rejects_bad_filters(): void
    {
        $this->getJson('/api/movies/discover?genres=sci-fi&year_from=2026&year_to=2015&sort=random')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['genres', 'year_to', 'sort']);
    }
}
