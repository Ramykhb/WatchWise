<?php

namespace Tests\Feature\Tmdb\Repository;

use App\Tmdb\DTOs\Requests\MovieDiscoverQuery;
use App\Tmdb\DTOs\Requests\MovieListQuery;
use App\Tmdb\DTOs\Requests\MovieSearchQuery;
use App\Tmdb\Exceptions\MovieNotFound;
use App\Tmdb\Exceptions\TmdbUnavailable;
use App\Tmdb\InterfaceRepository\MovieRepositoryInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MovieRepositoryTest extends TestCase
{
    private function repository(): MovieRepositoryInterface
    {
        return app(MovieRepositoryInterface::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function page(): array
    {
        return ['page' => 1, 'total_pages' => 3, 'total_results' => 50, 'results' => [['id' => 1]]];
    }

    private function fakeTmdb(): void
    {
        Http::fake(['https://api.themoviedb.org/3/*' => Http::response($this->page())]);
    }

    public function test_list_calls_the_list_endpoint_and_returns_the_payload(): void
    {
        $this->fakeTmdb();

        $payload = $this->repository()->list(new MovieListQuery('top_rated', 3));

        $this->assertSame($this->page(), $payload);
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.themoviedb.org/3/movie/top_rated?')
            && $request['page'] === 3
            && $request['language'] === 'en-US'
            && $request['api_key'] === config('services.tmdb.key'));
    }

    public function test_search_sends_the_query_and_year(): void
    {
        $this->fakeTmdb();

        $this->repository()->search(new MovieSearchQuery('dune', 2, 2021));

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.themoviedb.org/3/search/movie?')
            && $request['query'] === 'dune'
            && $request['page'] === 2
            && $request['primary_release_year'] === 2021
            && $request['include_adult'] === 'false');
    }

    public function test_search_omits_the_year_when_not_given(): void
    {
        $this->fakeTmdb();

        $this->repository()->search(new MovieSearchQuery('dune'));

        Http::assertSent(fn (Request $request): bool => ! isset($request['primary_release_year']) && $request['page'] === 1);
    }

    public function test_discover_translates_every_filter(): void
    {
        $this->fakeTmdb();

        $this->repository()->discover(new MovieDiscoverQuery(
            genres: '28,12',
            yearFrom: 2000,
            yearTo: 2010,
            minRating: 3.5,
            runtimeMin: 90,
            runtimeMax: 150,
            language: 'fr',
            sort: 'release_date',
            page: 4,
        ));

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.themoviedb.org/3/discover/movie?')
            && $request['with_genres'] === '28,12'
            && $request['primary_release_date.gte'] === '2000-01-01'
            && $request['primary_release_date.lte'] === '2010-12-31'
            && (float) $request['vote_average.gte'] === 7.0
            && $request['with_runtime.gte'] === 90
            && $request['with_runtime.lte'] === 150
            && $request['with_original_language'] === 'fr'
            && $request['sort_by'] === 'primary_release_date.desc'
            && $request['page'] === 4
            && $request['include_adult'] === 'false'
            && ! isset($request['vote_count.gte']));
    }

    public function test_discover_drops_null_parameters(): void
    {
        $this->fakeTmdb();

        $this->repository()->discover(new MovieDiscoverQuery);

        Http::assertSent(function (Request $request): bool {
            foreach (['with_genres', 'primary_release_date.gte', 'primary_release_date.lte', 'vote_average.gte', 'with_runtime.gte', 'with_runtime.lte', 'with_original_language', 'vote_count.gte'] as $key) {
                if (isset($request[$key])) {
                    return false;
                }
            }

            return $request['sort_by'] === 'popularity.desc' && $request['page'] === 1;
        });
    }

    public function test_discover_maps_every_sort(): void
    {
        $this->fakeTmdb();

        foreach (['popularity' => 'popularity.desc', 'rating' => 'vote_average.desc', 'release_date' => 'primary_release_date.desc', 'title' => 'title.asc'] as $sort => $tmdbSort) {
            $this->repository()->discover(new MovieDiscoverQuery(sort: $sort));

            Http::assertSent(fn (Request $request): bool => $request['sort_by'] === $tmdbSort);
        }
    }

    public function test_discover_applies_a_vote_floor_only_for_rating_and_title(): void
    {
        $this->fakeTmdb();

        $this->repository()->discover(new MovieDiscoverQuery(sort: 'rating'));
        Http::assertSent(fn (Request $request): bool => $request['sort_by'] === 'vote_average.desc' && $request['vote_count.gte'] === 100);

        $this->repository()->discover(new MovieDiscoverQuery(sort: 'title'));
        Http::assertSent(fn (Request $request): bool => $request['sort_by'] === 'title.asc' && $request['vote_count.gte'] === 100);

        $this->repository()->discover(new MovieDiscoverQuery(sort: 'popularity'));
        Http::assertSent(fn (Request $request): bool => $request['sort_by'] === 'popularity.desc' && ! isset($request['vote_count.gte']));
    }

    public function test_a_tmdb_error_throws_tmdb_unavailable(): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response([], 500)]);

        $this->expectException(TmdbUnavailable::class);

        $this->repository()->list(new MovieListQuery);
    }

    public function test_a_connection_failure_throws_tmdb_unavailable(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->expectException(TmdbUnavailable::class);

        $this->repository()->search(new MovieSearchQuery('dune'));
    }

    public function test_find_asks_for_the_movie_with_its_extras_in_one_call(): void
    {
        Http::fake(['https://api.themoviedb.org/3/movie/438631*' => Http::response(['id' => 438631, 'title' => 'Dune'])]);

        $payload = $this->repository()->find(438631);

        $this->assertSame(['id' => 438631, 'title' => 'Dune'], $payload);
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.themoviedb.org/3/movie/438631?')
            && $request['append_to_response'] === 'credits,videos,recommendations,similar,release_dates');
        Http::assertSentCount(1);
    }

    public function test_find_throws_movie_not_found_for_an_unknown_id(): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response(['status_message' => 'Not found'], 404)]);

        $this->expectException(MovieNotFound::class);

        $this->repository()->find(999999999);
    }

    public function test_find_throws_tmdb_unavailable_when_tmdb_fails(): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response([], 500)]);

        $this->expectException(TmdbUnavailable::class);

        $this->repository()->find(438631);
    }
}
