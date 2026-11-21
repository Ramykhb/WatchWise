<?php

namespace Tests\Feature\Movie;

use App\Models\LocalMovie;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\UsesMovieCatalogue;
use Tests\Feature\Social\SocialTestCase;

class LocalMovieOverridesTest extends SocialTestCase
{
    use UsesMovieCatalogue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useRealCatalogue();
        Http::preventStrayRequests();
        $payload = [
            'id' => 550, 'title' => 'Provider title', 'overview' => 'Provider overview', 'release_date' => '2000-01-01',
            'runtime' => 120, 'genres' => [['id' => 18, 'name' => 'Drama']], 'vote_average' => 8, 'vote_count' => 100,
            'credits' => ['crew' => [['id' => 42, 'name' => 'Provider Director', 'job' => 'Director']],
                'cast' => [['id' => 43, 'name' => 'Provider Actor', 'character' => 'Hero']]],
        ];
        Http::fake([
            '*movie/550*' => Http::response($payload),
            '*tv/550*' => Http::response([...$payload, 'title' => null, 'name' => 'Provider show', 'created_by' => [['id' => 44, 'name' => 'TV Creator']]]),
            '*movie/popular*' => Http::response(['page' => 1, 'total_pages' => 1, 'total_results' => 1, 'results' => [$payload]]),
        ]);
        LocalMovie::factory()->create(['tmdb_id' => 550, 'title' => 'Provider title', 'overview' => 'Provider overview',
            'director' => 'Provider Director', 'runtime' => 120, 'release_date' => '2000-01-01', 'genre_ids' => [18]]);
    }

    public function test_admin_edits_overlay_warm_provider_cache_and_keep_cast_and_rating(): void
    {
        $this->getJson('/api/movies/550')->assertOk()->assertJsonPath('title', 'Provider title');
        $this->as(User::factory()->admin()->create())->patchJson('/api/admin/movies/550', [
            'title' => 'Local title', 'overview' => 'Local overview', 'director' => 'Local Director',
            'runtime' => 150, 'release_date' => '2001-02-03', 'genre_ids' => [35],
        ])->assertOk();
        $this->getJson('/api/movies/550')->assertOk()->assertJsonPath('title', 'Local title')
            ->assertJsonPath('overview', 'Local overview')->assertJsonPath('runtime', 150)
            ->assertJsonPath('release_date', '2001-02-03')->assertJsonPath('year', 2001)
            ->assertJsonPath('genre_ids', [35])->assertJsonPath('genres.0.name', 'Comedy')
            ->assertJsonPath('directors.0.name', 'Local Director')->assertJsonPath('directors.0.id', null)
            ->assertJsonPath('cast.0.id', 43)->assertJsonPath('rating', 4);
        Http::assertSentCount(1);
    }

    public function test_explicit_clears_do_not_fall_back_to_provider_values(): void
    {
        $this->as(User::factory()->admin()->create())->patchJson('/api/admin/movies/550', [
            'overview' => null, 'runtime' => null, 'director' => null, 'release_date' => null, 'genre_ids' => [],
        ])->assertOk();
        $this->getJson('/api/movies/550')->assertOk()->assertJsonPath('title', 'Provider title')
            ->assertJsonPath('overview', '')->assertJsonPath('runtime', null)->assertJsonPath('release_date', null)
            ->assertJsonPath('year', null)->assertJsonPath('genres', [])->assertJsonPath('genre_ids', [])->assertJsonPath('directors', []);
    }

    public function test_sequential_edits_only_override_fields_that_were_actually_edited(): void
    {
        LocalMovie::where('tmdb_id', 550)->update(['runtime' => 90]);
        $this->as(User::factory()->admin()->create())->patchJson('/api/admin/movies/550', ['title' => 'Local title'])->assertOk();
        $this->patchJson('/api/admin/movies/550', ['overview' => 'Local overview'])->assertOk();
        $this->getJson('/api/movies/550')->assertOk()->assertJsonPath('runtime', 120)
            ->assertJsonPath('title', 'Local title')->assertJsonPath('overview', 'Local overview');
        $this->assertSame(['title', 'overview'], LocalMovie::where('tmdb_id', 550)->first()->override_fields);
    }

    public function test_legacy_edits_are_respected_and_do_not_change_a_tv_show_with_the_same_id(): void
    {
        LocalMovie::where('tmdb_id', 550)->update(['title' => 'Legacy title', 'edited_at' => now()]);
        $this->getJson('/api/movies/550')->assertOk()->assertJsonPath('title', 'Legacy title');
        $this->getJson('/api/movies/550?type=tv')->assertOk()->assertJsonPath('title', 'Provider show')
            ->assertJsonPath('directors.0.name', 'TV Creator');
    }

    public function test_browse_results_apply_edits_after_cache_without_changing_the_provider_cache(): void
    {
        $this->getJson('/api/movies?list=popular')->assertOk()->assertJsonPath('movies.0.title', 'Provider title');
        $this->as(User::factory()->admin()->create())->patchJson('/api/admin/movies/550', ['title' => 'Local title'])->assertOk();
        $this->getJson('/api/movies?list=popular')->assertOk()->assertJsonPath('movies.0.title', 'Local title');
        LocalMovie::where('tmdb_id', 550)->update(['edited_at' => null, 'override_fields' => null]);
        $this->getJson('/api/movies?list=popular')->assertOk()->assertJsonPath('movies.0.title', 'Provider title');
        Http::assertSentCount(1);
    }
}
