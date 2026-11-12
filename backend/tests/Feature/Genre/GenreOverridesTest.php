<?php

namespace Tests\Feature\Genre;

use App\Genre\InterfaceServices\GenreServiceInterface;
use App\Models\GenreOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** What members see of TMDB's genres once staff's overrides are layered on top. */
class GenreOverridesTest extends TestCase
{
    use RefreshDatabase;

    /** TMDB's order on purpose: neither alphabetical nor by id, so any re-sorting shows. */
    private const MOVIE_GENRES = [
        ['id' => 53, 'name' => 'Thriller'],
        ['id' => 10770, 'name' => 'TV Movie'],
        ['id' => 35, 'name' => 'Comedy'],
        ['id' => 18, 'name' => 'Drama'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tmdb.url' => 'https://api.themoviedb.org/3', 'services.tmdb.key' => 'test-key']);
        Http::fake([
            'https://api.themoviedb.org/3/genre/movie/list*' => Http::response(['genres' => self::MOVIE_GENRES]),
            'https://api.themoviedb.org/3/genre/tv/list*' => Http::response(['genres' => [
                ['id' => 18, 'name' => 'Drama'],
                ['id' => 10759, 'name' => 'Action & Adventure'],
            ]]),
        ]);
    }

    /** @return list<array{id: int, name: string}> */
    private function movieGenres(string $query = ''): array
    {
        return $this->getJson('/api/genres'.$query)->assertOk()->json('genres');
    }

    /** @return list<int> */
    private function movieGenreIds(string $query = ''): array
    {
        return array_column($this->movieGenres($query), 'id');
    }

    public function test_the_list_is_the_tmdb_list_unchanged_when_there_are_no_overrides(): void
    {
        $this->getJson('/api/genres')->assertOk()->assertExactJson(['genres' => self::MOVIE_GENRES]);
    }

    public function test_a_rename_replaces_the_name_without_moving_the_genre(): void
    {
        GenreOverride::factory()->create(['tmdb_id' => 53, 'name' => 'Zzz Suspense']);
        GenreOverride::factory()->create(['tmdb_id' => 18, 'name' => 'Aaa Serious']);

        $this->assertSame(
            [['id' => 53, 'name' => 'Zzz Suspense'], ['id' => 10770, 'name' => 'TV Movie'], ['id' => 35, 'name' => 'Comedy'], ['id' => 18, 'name' => 'Aaa Serious']],
            $this->movieGenres(),
        );
    }

    public function test_a_hidden_genre_is_dropped_from_the_list(): void
    {
        GenreOverride::factory()->unnamed()->hidden()->create(['tmdb_id' => 10770]);

        $this->assertSame([53, 35, 18], $this->movieGenreIds());
    }

    public function test_genres_with_a_position_come_first_in_that_order_and_the_rest_keep_tmdb_order(): void
    {
        GenreOverride::factory()->unnamed()->at(2)->create(['tmdb_id' => 18]);
        GenreOverride::factory()->unnamed()->at(1)->create(['tmdb_id' => 35]);

        $this->assertSame([35, 18, 53, 10770], $this->movieGenreIds());
    }

    public function test_genres_sharing_a_position_keep_tmdb_order(): void
    {
        GenreOverride::factory()->unnamed()->at(1)->create(['tmdb_id' => 18]);
        GenreOverride::factory()->unnamed()->at(1)->create(['tmdb_id' => 10770]);

        $this->assertSame([10770, 18, 53, 35], $this->movieGenreIds());
    }

    public function test_renaming_a_placed_genre_keeps_its_position_and_renaming_another_does_not_move_it(): void
    {
        GenreOverride::factory()->at(1)->create(['tmdb_id' => 18, 'name' => 'Zzz Serious']);
        GenreOverride::factory()->create(['tmdb_id' => 10770, 'name' => 'Aaa Telefilm']);

        $this->assertSame(
            [['id' => 18, 'name' => 'Zzz Serious'], ['id' => 53, 'name' => 'Thriller'], ['id' => 10770, 'name' => 'Aaa Telefilm'], ['id' => 35, 'name' => 'Comedy']],
            $this->movieGenres(),
        );
    }

    public function test_the_response_shape_is_unchanged(): void
    {
        GenreOverride::factory()->at(1)->create(['tmdb_id' => 35, 'name' => 'Laughs']);

        $this->getJson('/api/genres')->assertOk()->assertExactJson(['genres' => [
            ['id' => 35, 'name' => 'Laughs'],
            ['id' => 53, 'name' => 'Thriller'],
            ['id' => 10770, 'name' => 'TV Movie'],
            ['id' => 18, 'name' => 'Drama'],
        ]]);
    }

    public function test_the_tv_list_uses_only_tv_overrides(): void
    {
        GenreOverride::factory()->tv()->create(['tmdb_id' => 18, 'name' => 'Serious']);
        GenreOverride::factory()->create(['tmdb_id' => 10759, 'name' => 'Movie override on a tv id']);

        $this->getJson('/api/genres?type=tv')->assertOk()->assertExactJson(['genres' => [
            ['id' => 18, 'name' => 'Serious'],
            ['id' => 10759, 'name' => 'Action & Adventure'],
        ]]);
        $this->assertSame('Drama', $this->movieGenres()[3]['name']);
    }

    public function test_a_change_shows_on_the_next_request_while_tmdb_is_fetched_once(): void
    {
        $this->assertSame('Comedy', collect($this->movieGenres())->firstWhere('id', 35)['name']);

        GenreOverride::factory()->create(['tmdb_id' => 35, 'name' => 'Laughs']);

        $this->assertSame('Laughs', collect($this->movieGenres())->firstWhere('id', 35)['name']);
        Http::assertSentCount(1);
    }

    public function test_include_hidden_keeps_hidden_genres_with_their_display_names_and_the_same_order(): void
    {
        GenreOverride::factory()->hidden()->at(1)->create(['tmdb_id' => 18, 'name' => 'Serious']);
        GenreOverride::factory()->create(['tmdb_id' => 35, 'name' => 'Laughs']);
        GenreOverride::factory()->unnamed()->hidden()->create(['tmdb_id' => 10770]);

        $this->assertSame([53, 35], array_slice($this->movieGenreIds(), 0, 2));
        $this->assertCount(2, $this->movieGenres());
        $this->getJson('/api/genres?include_hidden=1')->assertOk()->assertExactJson(['genres' => [
            ['id' => 18, 'name' => 'Serious'],
            ['id' => 53, 'name' => 'Thriller'],
            ['id' => 10770, 'name' => 'TV Movie'],
            ['id' => 35, 'name' => 'Laughs'],
        ]]);
    }

    public function test_include_hidden_defaults_to_off_and_accepts_only_booleans(): void
    {
        GenreOverride::factory()->unnamed()->hidden()->create(['tmdb_id' => 35]);

        $this->assertSame([53, 10770, 18], $this->movieGenreIds('?include_hidden=0'));
        $this->assertSame([53, 10770, 35, 18], $this->movieGenreIds('?include_hidden=1'));
        $this->getJson('/api/genres?include_hidden=yes')->assertUnprocessable()->assertJsonValidationErrors('include_hidden');
        $this->getJson('/api/genres?include_hidden[]=1')->assertUnprocessable();
    }

    public function test_include_hidden_works_for_the_tv_list(): void
    {
        GenreOverride::factory()->tv()->unnamed()->hidden()->create(['tmdb_id' => 18]);

        $this->getJson('/api/genres?type=tv')->assertOk()->assertJsonCount(1, 'genres');
        $this->getJson('/api/genres?type=tv&include_hidden=1')->assertOk()->assertJsonCount(2, 'genres');
    }

    public function test_the_service_keeps_the_raw_list_and_the_hidden_genres_for_its_other_callers(): void
    {
        GenreOverride::factory()->hidden()->create(['tmdb_id' => 35, 'name' => 'Laughs']);
        $service = app(GenreServiceInterface::class);

        $this->assertSame([53, 10770, 18], $this->movieGenreIds());
        $this->assertSame('Comedy', collect($service->tmdb())->firstWhere('id', 35)->name);
        $this->assertCount(4, $service->tmdb());
        $this->assertSame('Laughs', collect($service->allIncludingHidden())->firstWhere('id', 35)->name);
        $this->assertCount(4, $service->allIncludingHidden());
    }

    public function test_overrides_leave_the_cached_tmdb_list_untouched(): void
    {
        GenreOverride::factory()->hidden()->create(['tmdb_id' => 35, 'name' => 'Laughs']);

        $this->movieGenres();

        $this->assertSame(self::MOVIE_GENRES, Cache::get('tmdb.movie_genres'));
    }
}
