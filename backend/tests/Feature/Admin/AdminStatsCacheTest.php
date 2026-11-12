<?php

namespace Tests\Feature\Admin;

use App\Genre\DTOs\Responses\GenreResponse;
use App\Genre\InterfaceServices\GenreServiceInterface;
use App\Models\LocalMovie;
use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Support\Facades\DB;
use Mockery;

class AdminStatsCacheTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(GenreServiceInterface::class, Mockery::mock(GenreServiceInterface::class, fn ($mock) => $mock
            ->shouldReceive('all')->andReturn([new GenreResponse(18, 'Drama')])));
    }

    private function watch(int $tmdbId, int $times): void
    {
        foreach (User::factory()->count($times)->create() as $user) {
            Watchlist::create(['user_id' => $user->id, 'tmdb_id' => $tmdbId, 'status' => 'watched', 'watched_at' => now()]);
        }
    }

    public function test_a_second_request_inside_the_ttl_is_served_from_the_cache_without_queries(): void
    {
        $admin = $this->admin();
        $this->as($admin)->getJson('/api/admin/stats')->assertOk()->assertJsonPath('users_total', 1);

        User::factory()->count(3)->create();

        DB::enableQueryLog();
        $cached = $this->getJson('/api/admin/stats')->assertOk();
        $statements = array_column(DB::getQueryLog(), 'query');

        $cached->assertJsonPath('users_total', 1);
        $this->assertSame([], array_filter($statements, fn (string $sql): bool => str_contains($sql, 'count(') || str_contains($sql, 'watchlist')));
    }

    public function test_the_cached_response_keeps_the_exact_shape_of_a_fresh_one(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 10, 'title' => 'Popular', 'genre_ids' => [18]]);
        $this->watch(10, 2);
        $admin = $this->admin();

        $fresh = $this->as($admin)->getJson('/api/admin/stats?days=7')->assertOk()->json();
        $cached = $this->getJson('/api/admin/stats?days=7')->assertOk()->json();

        $this->assertSame('Popular', $fresh['most_watched'][0]['title']);
        $this->assertSame($fresh, $cached);
    }

    public function test_each_window_is_cached_separately(): void
    {
        $admin = $this->admin();
        $this->as($admin)->getJson('/api/admin/stats?days=7')->assertJsonPath('users_total', 1);
        User::factory()->create();

        $this->getJson('/api/admin/stats?days=7')->assertJsonPath('users_total', 1);
        $this->getJson('/api/admin/stats?days=14')->assertJsonPath('users_total', 2)->assertJsonCount(14, 'activity');
        $this->getJson('/api/admin/stats?range=7')->assertJsonPath('users_total', 2)->assertJsonPath('range.name', '7');
        $this->getJson('/api/admin/stats?days=7')->assertJsonPath('users_total', 1);
    }

    public function test_the_numbers_are_worked_out_again_once_the_ttl_has_passed(): void
    {
        $admin = $this->admin();
        $this->as($admin)->getJson('/api/admin/stats')->assertJsonPath('users_total', 1);
        User::factory()->create();

        $this->travel(61)->seconds();

        $this->getJson('/api/admin/stats')->assertJsonPath('users_total', 2);
    }

    public function test_editing_a_film_retires_the_cached_numbers(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 10, 'title' => 'Popular']);
        $this->watch(10, 2);
        $admin = $this->admin();
        $this->as($admin)->getJson('/api/admin/stats?days=7')->assertJsonCount(1, 'most_watched');
        $this->getJson('/api/admin/stats?days=14')->assertJsonCount(1, 'most_watched');

        $this->patchJson('/api/admin/movies/10', ['is_hidden' => true])->assertOk();

        $this->getJson('/api/admin/stats?days=7')->assertJsonCount(0, 'most_watched');
        $this->getJson('/api/admin/stats?days=14')->assertJsonCount(0, 'most_watched');
    }

    public function test_moderating_and_deleting_reviews_retire_the_cached_numbers(): void
    {
        $admin = $this->admin();
        $pending = $this->review(['tmdb_id' => 1, 'is_approved' => false]);
        $other = $this->review(['tmdb_id' => 2, 'is_approved' => false]);
        $this->as($admin)->getJson('/api/admin/stats')->assertJsonPath('reviews_pending', 2);

        $this->patchJson("/api/admin/reviews/{$pending->id}", ['status' => 'approved'])->assertOk();
        $this->getJson('/api/admin/stats')->assertJsonPath('reviews_pending', 1);

        $this->deleteJson("/api/admin/reviews/{$other->id}")->assertSuccessful();
        $this->getJson('/api/admin/stats')->assertJsonPath('reviews_pending', 0)->assertJsonPath('reviews_total', 1);
    }

    public function test_deleting_a_user_retires_the_cached_numbers(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create();
        $this->as($admin)->getJson('/api/admin/stats')->assertJsonPath('users_total', 2);

        $this->deleteJson("/api/admin/users/{$member->id}")->assertOk();

        $this->getJson('/api/admin/stats')->assertJsonPath('users_total', 1);
    }
}
