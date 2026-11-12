<?php

namespace Tests\Feature\Movie;

use App\Models\LocalMovie;
use App\Models\User;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesMovieCatalogue;
use Tests\Feature\Social\SocialTestCase;

class HiddenMoviesTest extends SocialTestCase
{
    use UsesMovieCatalogue;

    private const HIDDEN = 101;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tmdb.key' => 'test-key']);
        $this->useRealCatalogue();
        $this->hide(self::HIDDEN);
    }

    /**
     * A TMDB page with the hidden movie between two visible ones.
     *
     * @return array<string, mixed>
     */
    private function page(): array
    {
        return [
            'page' => 1,
            'total_pages' => 3,
            'total_results' => 60,
            'results' => [
                ['id' => 100, 'title' => 'Visible One', 'release_date' => '2026-03-06', 'vote_average' => 8, 'popularity' => 30],
                ['id' => self::HIDDEN, 'title' => 'Hidden Film', 'release_date' => '2026-03-07', 'vote_average' => 7, 'popularity' => 20],
                ['id' => 102, 'title' => 'Visible Two', 'release_date' => '2026-03-08', 'vote_average' => 6, 'popularity' => 10],
            ],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function browseEndpoints(): array
    {
        return [
            'popular' => ['/api/movies?list=popular'],
            'upcoming' => ['/api/movies?list=upcoming'],
            'trending' => ['/api/movies/trending'],
            'search' => ['/api/movies/search?q=film'],
            'discover' => ['/api/movies/discover?sort=rating'],
            'browse by genre' => ['/api/movies/discover?genres=878'],
        ];
    }

    /**
     * @return list<int>
     */
    private function ids(string $url): array
    {
        return collect($this->getJson($url)->assertOk()->json('movies'))->pluck('id')->all();
    }

    #[DataProvider('browseEndpoints')]
    public function test_a_hidden_movie_is_left_out_of_browsing(string $url): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response($this->page())]);

        $this->assertSame([100, 102], $this->ids($url));
    }

    public function test_a_hidden_movie_is_left_out_of_combined_movie_and_tv_pages(): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response($this->page())]);

        foreach (['/api/movies/search?q=film&type=all', '/api/movies/discover?type=all'] as $url) {
            $movies = collect($this->getJson($url)->assertOk()->json('movies'));

            $this->assertSame([100, 100, self::HIDDEN, 102, 102], $movies->pluck('id')->sort()->values()->all(), $url);
            $this->assertSame(['tv'], $movies->where('id', self::HIDDEN)->pluck('media_type')->all(), $url);
        }
    }

    /** @return array<string, array{string, int}> */
    public static function combinedPaginationCases(): array
    {
        return [
            'search with one hidden movie' => ['/api/movies/search?q=film&type=all', 1],
            'discover with one hidden movie' => ['/api/movies/discover?type=all', 1],
            'search with a hidden source page' => ['/api/movies/search?q=film&type=all', 25],
            'discover with a hidden source page' => ['/api/movies/discover?type=all', 25],
            'search with every movie hidden' => ['/api/movies/search?q=film&type=all', 40],
            'discover with every movie hidden' => ['/api/movies/discover?type=all', 40],
        ];
    }

    #[DataProvider('combinedPaginationCases')]
    public function test_combined_pages_do_not_skip_or_repeat_visible_titles_when_movies_are_hidden(string $url, int $hiddenCount): void
    {
        LocalMovie::query()->delete();
        foreach (range(1, $hiddenCount) as $id) {
            $this->hide($id);
        }
        $movies = array_map(fn (int $id): array => ['id' => $id, 'title' => 'Movie '.$id, 'popularity' => 1000 - $id], range(1, 40));
        $shows = array_map(fn (int $id): array => ['id' => $id, 'name' => 'Show '.$id, 'popularity' => 200 - $id], range(101, 120));
        Http::preventStrayRequests();
        $respond = function (Request $request) use ($movies, $shows): PromiseInterface {
            $titles = str_contains($request->url(), '/tv?') ? $shows : $movies;

            return Http::response([
                'page' => (int) $request['page'],
                'total_results' => count($titles),
                'total_pages' => (int) ceil(count($titles) / 20),
                'results' => array_slice($titles, ((int) $request['page'] - 1) * 20, 20),
            ]);
        };
        Http::fake([
            'https://api.themoviedb.org/3/search/*' => $respond,
            'https://api.themoviedb.org/3/discover/*' => $respond,
        ]);
        $expected = [
            ...array_map(fn (int $id): string => 'movie:'.$id, array_column(array_slice($movies, $hiddenCount), 'id')),
            ...array_map(fn (int $id): string => 'tv:'.$id, range(101, 120)),
        ];

        foreach ([1, 2, 3] as $page) {
            $titles = $this->getJson($url.'&page='.$page)->assertOk()->json('movies');

            $this->assertSame(
                array_slice($expected, ($page - 1) * 20, 20),
                array_map(fn (array $title): string => $title['media_type'].':'.$title['id'], $titles),
                'Combined page '.$page,
            );
        }
    }

    public function test_the_total_drops_by_the_hidden_titles_on_the_page_but_the_page_count_stays(): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response($this->page())]);

        $this->getJson('/api/movies/search?q=film')
            ->assertOk()
            ->assertJsonPath('total_results', 59)
            ->assertJsonPath('total_pages', 3);
        $this->getJson('/api/movies/search?q=film&type=all')
            ->assertOk()
            ->assertJsonPath('total_results', 119)
            ->assertJsonPath('total_pages', 6);
        $this->getJson('/api/movies/search?q=film&type=tv')->assertJsonPath('total_results', 60);
    }

    public function test_the_total_never_goes_below_zero(): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response([
            'page' => 1, 'total_pages' => 1, 'total_results' => 0,
            'results' => [['id' => self::HIDDEN, 'title' => 'Hidden Film']],
        ])]);

        $this->getJson('/api/movies/search?q=film')
            ->assertOk()
            ->assertJsonPath('total_results', 0)
            ->assertJsonCount(0, 'movies');
    }

    public function test_a_tv_show_that_shares_a_hidden_movie_id_stays_visible(): void
    {
        Http::fake(['https://api.themoviedb.org/*' => Http::response($this->page())]);

        $this->assertSame([100, self::HIDDEN, 102], $this->ids('/api/movies/search?q=film&type=tv'));
        $this->assertSame([100, self::HIDDEN, 102], $this->ids('/api/movies/discover?type=tv'));
        $this->assertSame([100, self::HIDDEN, 102], $this->ids('/api/movies/trending?type=tv'));
    }

    public function test_a_hidden_movie_still_opens_and_is_left_out_of_similar_movies(): void
    {
        Http::fake(['https://api.themoviedb.org/3/movie/*' => Http::response([
            'id' => self::HIDDEN,
            'title' => 'Hidden Film',
            'recommendations' => ['results' => [
                ['id' => 100, 'title' => 'Visible One'],
                ['id' => self::HIDDEN, 'title' => 'Hidden Film'],
                ['id' => 102, 'title' => 'Visible Two'],
            ]],
        ])]);

        $this->getJson('/api/movies/'.self::HIDDEN)
            ->assertOk()
            ->assertJsonPath('id', self::HIDDEN)
            ->assertJsonPath('title', 'Hidden Film')
            ->assertJsonCount(2, 'similar')
            ->assertJsonPath('similar.0.id', 100)
            ->assertJsonPath('similar.1.id', 102);
    }

    public function test_a_movie_that_is_not_in_the_catalogue_is_never_hidden(): void
    {
        LocalMovie::query()->delete();
        Http::fake(['https://api.themoviedb.org/*' => Http::response($this->page())]);

        $this->assertSame([100, self::HIDDEN, 102], $this->ids('/api/movies?list=popular'));
    }

    public function test_hiding_and_showing_a_film_applies_on_the_next_request_even_when_tmdb_answers_are_cached(): void
    {
        LocalMovie::query()->delete();
        LocalMovie::factory()->create(['tmdb_id' => self::HIDDEN]);
        Http::fake(['https://api.themoviedb.org/*' => Http::response($this->page())]);
        $admin = User::factory()->admin()->create();

        $this->assertSame([100, self::HIDDEN, 102], $this->ids('/api/movies?list=popular'));
        $this->assertSame([100, self::HIDDEN, 102], $this->ids('/api/movies/trending'));

        $this->as($admin)->patchJson('/api/admin/movies/'.self::HIDDEN, ['is_hidden' => true])->assertOk();

        $this->assertSame([100, 102], $this->ids('/api/movies?list=popular'));
        $this->assertSame([100, 102], $this->ids('/api/movies/trending'));

        $this->as($admin)->patchJson('/api/admin/movies/'.self::HIDDEN, ['is_hidden' => false])->assertOk();

        $this->assertSame([100, self::HIDDEN, 102], $this->ids('/api/movies?list=popular'));
        Http::assertSentCount(2);
    }

    public function test_admins_still_see_hidden_movies_in_the_catalogue(): void
    {
        $this->as(User::factory()->admin()->create())->getJson('/api/admin/movies')
            ->assertOk()
            ->assertJsonPath('data.0.tmdb_id', self::HIDDEN)
            ->assertJsonPath('data.0.is_hidden', true);
    }
}
