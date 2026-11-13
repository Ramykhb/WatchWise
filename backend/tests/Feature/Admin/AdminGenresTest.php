<?php

namespace Tests\Feature\Admin;

use App\Admin\Support\AdminAction;
use App\Admin\Support\AdminSubjectType;
use App\Genre\InterfaceRepository\GenreOverrideRepositoryInterface;
use App\Genre\InterfaceServices\GenreServiceInterface;
use App\Genre\Repository\GenreOverrideRepository;
use App\Models\AdminAuditLog;
use App\Models\GenreOverride;
use App\Models\LocalMovie;
use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** The staff genres page: TMDB's genres merged with the local overrides, and changing or resetting them. */
class AdminGenresTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tmdb.url' => 'https://api.themoviedb.org/3', 'services.tmdb.key' => 'test-key']);
        $this->fakeTmdb();
    }

    private function fakeTmdb(): void
    {
        Http::fake([
            'https://api.themoviedb.org/3/genre/movie/list*' => Http::response(['genres' => [
                ['id' => 28, 'name' => 'Action'],
                ['id' => 35, 'name' => 'Comedy'],
                ['id' => 18, 'name' => 'Drama'],
            ]]),
            'https://api.themoviedb.org/3/genre/tv/list*' => Http::response(['genres' => [
                ['id' => 18, 'name' => 'Drama'],
                ['id' => 10759, 'name' => 'Action & Adventure'],
            ]]),
        ]);
    }

    /** Replaces the TMDB fake set up for each test with one where every request fails. */
    private function fakeTmdbDown(): void
    {
        Http::swap(new HttpFactory);
        Http::fake(['https://api.themoviedb.org/*' => Http::response([], 500)]);
    }

    private function moderator(): User
    {
        return User::factory()->create(['role' => 'moderator']);
    }

    private function onlyEntry(): AdminAuditLog
    {
        $this->assertDatabaseCount('admin_audit_logs', 1);

        return AdminAuditLog::query()->firstOrFail();
    }

    public function test_a_guest_is_unauthorised_and_a_member_forbidden(): void
    {
        $this->getJson('/api/admin/genres')->assertUnauthorized();
        $this->patchJson('/api/admin/genres/movie/28', ['is_hidden' => true])->assertUnauthorized();
        $this->deleteJson('/api/admin/genres/movie/28')->assertUnauthorized();

        $member = User::factory()->create();
        $this->as($member)->getJson('/api/admin/genres')->assertForbidden();
        $this->as($member)->patchJson('/api/admin/genres/movie/28', ['is_hidden' => true])->assertForbidden();
        $this->as($member)->deleteJson('/api/admin/genres/movie/28')->assertForbidden();
    }

    public function test_a_moderator_can_read_the_genres_but_not_change_them(): void
    {
        GenreOverride::factory()->create(['tmdb_id' => 28, 'name' => 'Fights']);
        $moderator = $this->moderator();

        $this->as($moderator)->getJson('/api/admin/genres')->assertOk()->assertJsonCount(3, 'genres');
        $this->as($moderator)->patchJson('/api/admin/genres/movie/28', ['is_hidden' => true])->assertForbidden();
        $this->as($moderator)->deleteJson('/api/admin/genres/movie/28')->assertForbidden();

        $this->assertDatabaseHas('genre_overrides', ['tmdb_id' => 28, 'name' => 'Fights', 'is_hidden' => false]);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_an_admin_sees_every_tmdb_genre_merged_with_its_override(): void
    {
        $editor = $this->admin();
        GenreOverride::factory()->at(2)->create(['tmdb_id' => 28, 'name' => 'Fights', 'updated_by' => $editor->id]);
        GenreOverride::factory()->unnamed()->hidden()->create(['tmdb_id' => 35]);

        $response = $this->as($this->admin())->getJson('/api/admin/genres')->assertOk();

        $response->assertJsonCount(3, 'genres')
            ->assertJsonPath('genres.0.tmdb_id', 28)
            ->assertJsonPath('genres.0.tmdb_name', 'Action')
            ->assertJsonPath('genres.0.name', 'Fights')
            ->assertJsonPath('genres.0.name_override', 'Fights')
            ->assertJsonPath('genres.0.is_hidden', false)
            ->assertJsonPath('genres.0.position', 2)
            ->assertJsonPath('genres.1.tmdb_id', 35)
            ->assertJsonPath('genres.1.name', 'Comedy')
            ->assertJsonPath('genres.1.name_override', null)
            ->assertJsonPath('genres.1.is_hidden', true)
            ->assertJsonPath('genres.2.tmdb_id', 18)
            ->assertJsonPath('genres.2.name', 'Drama')
            ->assertJsonPath('genres.2.is_hidden', false)
            ->assertJsonPath('genres.2.position', null)
            ->assertJsonPath('genres.2.updated_at', null);
        $this->assertNotNull($response->json('genres.0.updated_at'));
        $this->assertSame(
            ['tmdb_id', 'tmdb_name', 'name', 'name_override', 'is_hidden', 'position', 'movie_count', 'updated_at'],
            array_keys($response->json('genres.0')),
        );
    }

    public function test_the_type_picks_the_movie_or_tv_list_and_defaults_to_movie(): void
    {
        GenreOverride::factory()->tv()->create(['tmdb_id' => 18, 'name' => 'Serious']);
        $admin = $this->admin();

        $this->as($admin)->getJson('/api/admin/genres')->assertOk()->assertJsonPath('genres.2.name', 'Drama');
        $this->as($admin)->getJson('/api/admin/genres?type=movie')->assertOk()->assertJsonCount(3, 'genres');
        $this->as($admin)->getJson('/api/admin/genres?type=tv')
            ->assertOk()
            ->assertJsonCount(2, 'genres')
            ->assertJsonPath('genres.0.tmdb_id', 18)
            ->assertJsonPath('genres.0.name', 'Serious')
            ->assertJsonPath('genres.1.tmdb_name', 'Action & Adventure');
        $this->as($admin)->getJson('/api/admin/genres?type=anime')->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    public function test_the_movie_count_is_the_visible_catalogue_films_carrying_the_genre(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 1, 'genre_ids' => [28, 18]]);
        LocalMovie::factory()->create(['tmdb_id' => 2, 'genre_ids' => [18]]);
        LocalMovie::factory()->hidden()->create(['tmdb_id' => 3, 'genre_ids' => [18, 35]]);
        LocalMovie::factory()->create(['tmdb_id' => 4, 'genre_ids' => null]);
        LocalMovie::factory()->create(['tmdb_id' => 5, 'genre_ids' => [999]]);
        $admin = $this->admin();

        $counts = collect($this->as($admin)->getJson('/api/admin/genres')->assertOk()->json('genres'))->pluck('movie_count', 'tmdb_id');

        $this->assertSame([28 => 1, 35 => 0, 18 => 2], $counts->all());
        $tv = collect($this->as($admin)->getJson('/api/admin/genres?type=tv')->assertOk()->json('genres'))->pluck('movie_count', 'tmdb_id');
        $this->assertSame([18 => 0, 10759 => 0], $tv->all());
    }

    public function test_a_hidden_genre_keeps_its_film_count(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 1, 'genre_ids' => [35]]);
        GenreOverride::factory()->unnamed()->hidden()->create(['tmdb_id' => 35]);

        $this->as($this->admin())->getJson('/api/admin/genres')
            ->assertOk()
            ->assertJsonPath('genres.1.is_hidden', true)
            ->assertJsonPath('genres.1.movie_count', 1);
    }

    public function test_the_list_costs_the_same_queries_however_many_genres_and_films_there_are(): void
    {
        $admin = $this->admin();
        $token = $this->as($admin);
        $token->getJson('/api/admin/genres')->assertOk();

        DB::enableQueryLog();
        $token->getJson('/api/admin/genres')->assertOk();
        $few = count(DB::getQueryLog());

        foreach ([28, 35, 18] as $id) {
            GenreOverride::factory()->create(['tmdb_id' => $id]);
        }
        LocalMovie::factory()->count(25)->create(['genre_ids' => [28, 35, 18]]);

        DB::flushQueryLog();
        $token->getJson('/api/admin/genres')->assertOk();

        $this->assertSame($few, count(DB::getQueryLog()));
    }

    public function test_the_list_answers_502_when_tmdb_is_unavailable(): void
    {
        $this->fakeTmdbDown();

        $admin = $this->admin();
        $this->as($admin)->getJson('/api/admin/genres')
            ->assertStatus(502)
            ->assertJsonPath('message', 'Movie service is temporarily unavailable.');
        $this->as($admin)->patchJson('/api/admin/genres/movie/28', ['is_hidden' => true])->assertStatus(502);
        $this->assertDatabaseCount('genre_overrides', 0);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_an_admin_can_rename_hide_and_place_a_genre_in_one_request(): void
    {
        $admin = $this->admin();
        LocalMovie::factory()->create(['tmdb_id' => 1, 'genre_ids' => [28]]);

        $this->as($admin)->patchJson('/api/admin/genres/movie/28', ['name' => '  Fights ', 'is_hidden' => true, 'position' => 3])
            ->assertOk()
            ->assertJsonPath('genre.tmdb_id', 28)
            ->assertJsonPath('genre.tmdb_name', 'Action')
            ->assertJsonPath('genre.name', 'Fights')
            ->assertJsonPath('genre.name_override', 'Fights')
            ->assertJsonPath('genre.is_hidden', true)
            ->assertJsonPath('genre.position', 3)
            ->assertJsonPath('genre.movie_count', 1);

        $this->assertDatabaseHas('genre_overrides', [
            'type' => 'movie', 'tmdb_id' => 28, 'name' => 'Fights', 'is_hidden' => true, 'position' => 3, 'updated_by' => $admin->id,
        ]);
    }

    public function test_a_partial_update_leaves_the_other_fields_alone(): void
    {
        GenreOverride::factory()->at(4)->create(['tmdb_id' => 28, 'name' => 'Fights']);
        $admin = $this->admin();

        $this->as($admin)->patchJson('/api/admin/genres/movie/28', ['is_hidden' => true])->assertOk();
        $this->assertDatabaseHas('genre_overrides', ['tmdb_id' => 28, 'name' => 'Fights', 'position' => 4, 'is_hidden' => true]);

        $this->as($admin)->patchJson('/api/admin/genres/movie/28', ['position' => 1])->assertOk();
        $this->assertDatabaseHas('genre_overrides', ['tmdb_id' => 28, 'name' => 'Fights', 'position' => 1, 'is_hidden' => true]);
    }

    public function test_the_name_and_position_can_be_cleared_and_an_emptied_override_is_removed(): void
    {
        GenreOverride::factory()->at(4)->create(['tmdb_id' => 28, 'name' => 'Fights']);
        $admin = $this->admin();

        $this->as($admin)->patchJson('/api/admin/genres/movie/28', ['name' => null])
            ->assertOk()
            ->assertJsonPath('genre.name', 'Action')
            ->assertJsonPath('genre.name_override', null)
            ->assertJsonPath('genre.position', 4);
        $this->assertDatabaseHas('genre_overrides', ['tmdb_id' => 28, 'name' => null, 'position' => 4]);

        $this->as($admin)->patchJson('/api/admin/genres/movie/28', ['position' => null])
            ->assertOk()
            ->assertJsonPath('genre.position', null)
            ->assertJsonPath('genre.updated_at', null);
        $this->assertDatabaseCount('genre_overrides', 0);
    }

    public function test_an_empty_name_clears_it(): void
    {
        GenreOverride::factory()->create(['tmdb_id' => 28, 'name' => 'Fights', 'is_hidden' => true]);

        $this->as($this->admin())->patchJson('/api/admin/genres/movie/28', ['name' => '   '])
            ->assertOk()
            ->assertJsonPath('genre.name_override', null);

        $this->assertDatabaseHas('genre_overrides', ['tmdb_id' => 28, 'name' => null, 'is_hidden' => true]);
    }

    public function test_a_movie_and_a_tv_genre_with_the_same_id_are_separate_overrides(): void
    {
        $admin = $this->admin();

        $this->as($admin)->patchJson('/api/admin/genres/movie/18', ['name' => 'Movie drama'])->assertOk();
        $this->as($admin)->patchJson('/api/admin/genres/tv/18', ['name' => 'Tv drama'])->assertOk()->assertJsonPath('genre.movie_count', 0);

        $this->assertDatabaseHas('genre_overrides', ['type' => 'movie', 'tmdb_id' => 18, 'name' => 'Movie drama']);
        $this->assertDatabaseHas('genre_overrides', ['type' => 'tv', 'tmdb_id' => 18, 'name' => 'Tv drama']);
    }

    public function test_the_update_is_validated(): void
    {
        $admin = $this->admin();

        $this->as($admin)->patchJson('/api/admin/genres/movie/28', ['name' => str_repeat('a', 61)])->assertJsonValidationErrors('name');
        $this->as($admin)->patchJson('/api/admin/genres/movie/28', ['name' => ['x']])->assertJsonValidationErrors('name');
        $this->as($admin)->patchJson('/api/admin/genres/movie/28', ['is_hidden' => 'maybe'])->assertJsonValidationErrors('is_hidden');
        $this->as($admin)->patchJson('/api/admin/genres/movie/28', ['is_hidden' => null])->assertJsonValidationErrors('is_hidden');
        $this->as($admin)->patchJson('/api/admin/genres/movie/28', ['position' => -1])->assertJsonValidationErrors('position');
        $this->as($admin)->patchJson('/api/admin/genres/movie/28', ['position' => 1000])->assertJsonValidationErrors('position');
        $this->as($admin)->patchJson('/api/admin/genres/movie/28', ['position' => 'first'])->assertJsonValidationErrors('position');
        $this->as($admin)->patchJson('/api/admin/genres/movie/28', ['position' => true])->assertJsonValidationErrors('position');
        $this->as($admin)->patchJson('/api/admin/genres/movie/28', ['position' => 1.5])->assertJsonValidationErrors('position');

        $this->assertDatabaseCount('genre_overrides', 0);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_a_genre_tmdb_does_not_list_is_not_found(): void
    {
        $admin = $this->admin();

        $this->as($admin)->patchJson('/api/admin/genres/movie/99999', ['is_hidden' => true])->assertNotFound()->assertJsonPath('message', 'Genre not found.');
        $this->as($admin)->patchJson('/api/admin/genres/tv/28', ['is_hidden' => true])->assertNotFound();
        $this->as($admin)->patchJson('/api/admin/genres/anime/28', ['is_hidden' => true])->assertNotFound();
        $this->as($admin)->patchJson('/api/admin/genres/movie/abc', ['is_hidden' => true])->assertNotFound();

        $this->assertDatabaseCount('genre_overrides', 0);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_sending_what_a_genre_already_has_writes_and_records_nothing(): void
    {
        $this->as($this->admin())->patchJson('/api/admin/genres/movie/28', ['name' => null, 'is_hidden' => false, 'position' => null])->assertOk();
        $this->assertDatabaseCount('genre_overrides', 0);
        $this->assertDatabaseCount('admin_audit_logs', 0);

        GenreOverride::factory()->at(2)->create(['tmdb_id' => 28, 'name' => 'Fights']);

        $this->as($this->admin())->patchJson('/api/admin/genres/movie/28', ['name' => 'Fights', 'is_hidden' => false, 'position' => 2])->assertOk();
        $this->as($this->admin())->patchJson('/api/admin/genres/movie/28', [])->assertOk();

        $this->assertDatabaseCount('admin_audit_logs', 0);
        $this->assertDatabaseHas('genre_overrides', ['tmdb_id' => 28, 'updated_by' => null]);
    }

    public function test_an_update_is_recorded_with_only_the_fields_that_changed(): void
    {
        GenreOverride::factory()->at(4)->create(['tmdb_id' => 28, 'name' => 'Fights']);
        $admin = $this->admin();

        $this->as($admin)->patchJson('/api/admin/genres/movie/28', ['name' => 'Brawls', 'is_hidden' => false, 'position' => 4])->assertOk();

        $entry = $this->onlyEntry();
        $this->assertSame(AdminAction::GenreUpdated, $entry->action);
        $this->assertSame(AdminSubjectType::Genre, $entry->subject_type);
        $this->assertSame(28, $entry->subject_id);
        $this->assertSame('Action', $entry->subject_label);
        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertEquals(['name' => ['from' => 'Fights', 'to' => 'Brawls']], $entry->changes);
    }

    public function test_the_first_override_is_recorded_from_the_defaults_and_a_tv_genre_is_labelled_as_tv(): void
    {
        $this->as($this->admin())->patchJson('/api/admin/genres/tv/10759', ['is_hidden' => true, 'position' => 0])->assertOk();

        $entry = $this->onlyEntry();
        $this->assertSame('Action & Adventure (TV)', $entry->subject_label);
        $this->assertSame(10759, $entry->subject_id);
        $this->assertEquals([
            'is_hidden' => ['from' => false, 'to' => true],
            'position' => ['from' => null, 'to' => 0],
        ], $entry->changes);
    }

    public function test_the_audit_label_is_the_tmdb_name_even_when_the_genre_is_renamed(): void
    {
        GenreOverride::factory()->create(['tmdb_id' => 28, 'name' => 'Fights']);

        $this->as($this->admin())->patchJson('/api/admin/genres/movie/28', ['position' => 1])->assertOk();

        $this->assertSame('Action', $this->onlyEntry()->subject_label);
    }

    public function test_emptying_an_override_through_an_update_is_recorded_as_an_update(): void
    {
        GenreOverride::factory()->unnamed()->hidden()->create(['tmdb_id' => 28]);

        $this->as($this->admin())->patchJson('/api/admin/genres/movie/28', ['is_hidden' => false])->assertOk();

        $this->assertDatabaseCount('genre_overrides', 0);
        $this->assertEquals(['is_hidden' => ['from' => true, 'to' => false]], $this->onlyEntry()->changes);
    }

    public function test_an_update_leaves_the_cached_tmdb_list_alone(): void
    {
        $admin = $this->admin();
        $this->as($admin)->getJson('/api/admin/genres')->assertOk();

        $this->as($admin)->patchJson('/api/admin/genres/movie/28', ['name' => 'Fights'])->assertOk();
        $this->as($admin)->getJson('/api/admin/genres')->assertOk()->assertJsonPath('genres.0.tmdb_name', 'Action');

        Http::assertSentCount(1);
    }

    public function test_a_failing_audit_insert_rolls_back_a_genre_update_and_a_reset(): void
    {
        GenreOverride::factory()->at(4)->create(['tmdb_id' => 28, 'name' => 'Fights']);
        $admin = $this->admin();
        $this->failAuditInserts();

        $this->as($admin)->patchJson('/api/admin/genres/movie/28', ['name' => 'Brawls', 'is_hidden' => true])->assertStatus(500);
        $this->as($admin)->patchJson('/api/admin/genres/movie/35', ['is_hidden' => true])->assertStatus(500);
        $this->as($admin)->deleteJson('/api/admin/genres/movie/28')->assertStatus(500);

        $this->assertDatabaseHas('genre_overrides', ['tmdb_id' => 28, 'name' => 'Fights', 'is_hidden' => false, 'position' => 4]);
        $this->assertDatabaseMissing('genre_overrides', ['tmdb_id' => 35]);
        $this->assertDatabaseCount('genre_overrides', 1);
    }

    public function test_a_reset_removes_the_override_and_records_what_it_held(): void
    {
        GenreOverride::factory()->hidden()->at(2)->create(['tmdb_id' => 28, 'name' => 'Fights']);
        GenreOverride::factory()->create(['tmdb_id' => 35, 'name' => 'Laughs']);
        $admin = $this->admin();

        $this->as($admin)->deleteJson('/api/admin/genres/movie/28')->assertOk()->assertJsonPath('message', 'Genre reset.');

        $this->assertDatabaseMissing('genre_overrides', ['tmdb_id' => 28]);
        $this->assertDatabaseHas('genre_overrides', ['tmdb_id' => 35]);
        $entry = $this->onlyEntry();
        $this->assertSame(AdminAction::GenreReset, $entry->action);
        $this->assertSame(AdminSubjectType::Genre, $entry->subject_type);
        $this->assertSame(28, $entry->subject_id);
        $this->assertSame('Action', $entry->subject_label);
        $this->assertEquals([
            'name' => ['from' => 'Fights', 'to' => null],
            'is_hidden' => ['from' => true, 'to' => false],
            'position' => ['from' => 2, 'to' => null],
        ], $entry->changes);
    }

    public function test_a_reset_only_touches_its_own_type(): void
    {
        GenreOverride::factory()->create(['tmdb_id' => 18, 'name' => 'Movie drama']);
        GenreOverride::factory()->tv()->create(['tmdb_id' => 18, 'name' => 'Tv drama']);

        $this->as($this->admin())->deleteJson('/api/admin/genres/tv/18')->assertOk();

        $this->assertDatabaseHas('genre_overrides', ['type' => 'movie', 'tmdb_id' => 18]);
        $this->assertDatabaseMissing('genre_overrides', ['type' => 'tv', 'tmdb_id' => 18]);
        $this->assertSame('Drama (TV)', $this->onlyEntry()->subject_label);
    }

    public function test_resetting_a_genre_without_an_override_does_nothing_and_records_nothing(): void
    {
        $this->as($this->admin())->deleteJson('/api/admin/genres/movie/28')->assertOk();

        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_a_stale_override_can_be_reset_even_when_tmdb_no_longer_lists_the_genre_or_is_down(): void
    {
        GenreOverride::factory()->create(['tmdb_id' => 99999, 'name' => 'Gone']);
        GenreOverride::factory()->create(['tmdb_id' => 28, 'name' => 'Fights']);
        $this->fakeTmdbDown();
        $admin = $this->admin();

        $this->as($admin)->deleteJson('/api/admin/genres/movie/99999')->assertOk();
        $this->as($admin)->deleteJson('/api/admin/genres/movie/28')->assertOk();

        $this->assertDatabaseCount('genre_overrides', 0);
        $this->assertSame(['Genre 99999', 'Genre 28'], AdminAuditLog::orderBy('id')->pluck('subject_label')->all());
    }

    public function test_a_reset_without_an_override_does_not_ask_tmdb(): void
    {
        $this->as($this->admin())->deleteJson('/api/admin/genres/movie/28')->assertOk();

        Http::assertNothingSent();
    }

    public function test_a_lost_race_to_create_the_first_override_is_retried_against_the_winners_row(): void
    {
        $winner = GenreOverride::factory()->create(['tmdb_id' => 28, 'name' => 'Fights']);
        $this->app->bind(GenreOverrideRepositoryInterface::class, fn () => new class extends GenreOverrideRepository
        {
            private bool $staleRead = true;

            public function find(string $type, int $tmdbId, bool $lockForUpdate = false): ?GenreOverride
            {
                if ($this->staleRead) {
                    return null;
                }

                return parent::find($type, $tmdbId, $lockForUpdate);
            }

            public function save(string $type, int $tmdbId, array $values, ?int $updatedBy): GenreOverride
            {
                if ($this->staleRead) {
                    $this->staleRead = false;

                    throw new UniqueConstraintViolationException('testing', 'insert into genre_overrides', [], new RuntimeException('UNIQUE constraint failed'));
                }

                return parent::save($type, $tmdbId, $values, $updatedBy);
            }
        });
        $admin = $this->admin();

        $this->as($admin)->patchJson('/api/admin/genres/movie/28', ['is_hidden' => true])
            ->assertOk()
            ->assertJsonPath('genre.name_override', 'Fights')
            ->assertJsonPath('genre.is_hidden', true);

        $this->assertDatabaseCount('genre_overrides', 1);
        $this->assertDatabaseHas('genre_overrides', ['id' => $winner->id, 'name' => 'Fights', 'is_hidden' => true, 'updated_by' => $admin->id]);
        $this->assertEquals(['is_hidden' => ['from' => false, 'to' => true]], $this->onlyEntry()->changes);
    }

    public function test_a_reset_rejects_an_unknown_type(): void
    {
        $this->as($this->admin())->deleteJson('/api/admin/genres/anime/28')->assertNotFound();
    }

    public function test_admin_stats_name_genres_by_their_display_name_and_keep_a_hidden_genres_row(): void
    {
        $this->app->forgetInstance(GenreServiceInterface::class);
        GenreOverride::factory()->create(['tmdb_id' => 18, 'name' => 'Serious']);
        GenreOverride::factory()->unnamed()->hidden()->create(['tmdb_id' => 35]);
        LocalMovie::factory()->create(['tmdb_id' => 10, 'genre_ids' => [18, 35]]);
        LocalMovie::factory()->create(['tmdb_id' => 20, 'genre_ids' => [18]]);
        Watchlist::create(['user_id' => User::factory()->create()->id, 'tmdb_id' => 10, 'status' => 'watched', 'watched_at' => now()]);
        $this->review(['tmdb_id' => 20, 'content' => null, 'sentiment' => null, 'is_approved' => true]);

        $this->as($this->admin())->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('popular_genres.0.id', 18)
            ->assertJsonPath('popular_genres.0.name', 'Serious')
            ->assertJsonPath('popular_genres.1.id', 35)
            ->assertJsonPath('popular_genres.1.name', 'Comedy');
    }

    public function test_the_changes_reach_members_on_their_next_request_and_are_listed_in_the_audit_log(): void
    {
        $admin = $this->admin();

        $this->as($admin)->patchJson('/api/admin/genres/movie/35', ['is_hidden' => true])->assertOk();
        $this->getJson('/api/genres')->assertOk()->assertJsonCount(2, 'genres');

        $this->as($admin)->deleteJson('/api/admin/genres/movie/35')->assertOk();
        $this->getJson('/api/genres')->assertOk()->assertJsonCount(3, 'genres');

        $this->as($admin)->getJson('/api/admin/audit-log?action=genre.updated&subject_type=genre&subject_id=35')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
        $this->as($admin)->getJson('/api/admin/audit-log?action=genre.reset')->assertOk()->assertJsonPath('meta.total', 1);
    }
}
