<?php

namespace Tests\Feature\Admin;

use App\Admin\DTOs\Responses\AdminStatsResponse;
use App\Admin\InterfaceServices\AdminServiceInterface;
use App\Models\LocalMovie;
use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Support\Facades\DB;

/** The cached form of the dashboard: it must be the fresh response exactly, and building it must not grow with the data. */
class AdminStatsPayloadTest extends AdminStatsTestCase
{
    private function film(int $tmdbId = 10): void
    {
        LocalMovie::factory()->create(['tmdb_id' => $tmdbId, 'title' => 'Popular', 'genre_ids' => [18]]);
        Watchlist::create(['user_id' => User::factory()->create()->id, 'tmdb_id' => $tmdbId, 'status' => 'watched', 'watched_at' => now()]);
    }

    /** The SQL statements one request runs with nothing cached, after the member's own bookkeeping has been done once. */
    private function coldCacheQueries(): int
    {
        app(AdminServiceInterface::class)->forgetStats();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/admin/stats?range=7')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_a_cached_response_is_byte_identical_to_the_fresh_one_with_every_new_field_filled(): void
    {
        $this->seedWorld();
        $this->film();
        $admin = $this->admin();

        $fresh = $this->as($admin)->getJson('/api/admin/stats?range=7')->assertOk();
        DB::enableQueryLog();
        $cached = $this->getJson('/api/admin/stats?range=7')->assertOk();
        $countingQueries = array_filter(array_column(DB::getQueryLog(), 'query'), fn (string $sql): bool => str_contains($sql, 'count('));

        $this->assertSame([], $countingQueries);
        $this->assertSame($fresh->getContent(), $cached->getContent());
        $this->assertSame(3.5, $cached->json('ratings.average'));
        $this->assertSame(2, $cached->json('rooms.reported'));
        $this->assertSame(2, $cached->json('range.new_lists'));
    }

    public function test_the_payload_rebuilds_into_the_same_json_with_a_whole_number_average(): void
    {
        $this->rating(4);
        $this->rating(4);

        $payload = json_decode($this->as($this->admin())->getJson('/api/admin/stats')->assertOk()->getContent(), true);

        $this->assertEquals(4, $payload['ratings']['average']);
        $this->assertSame(json_encode($payload), json_encode(AdminStatsResponse::fromPayload($payload)));
    }

    public function test_the_payload_rebuilds_into_the_same_json_without_any_rating(): void
    {
        $payload = json_decode($this->as($this->admin())->getJson('/api/admin/stats')->assertOk()->getContent(), true);

        $this->assertNull($payload['ratings']['average']);
        $this->assertSame(json_encode($payload), json_encode(AdminStatsResponse::fromPayload($payload)));
    }

    public function test_the_cold_cache_query_count_does_not_grow_with_the_data(): void
    {
        $this->seedWorld();
        $this->film();
        $this->as($this->admin())->getJson('/api/admin/stats')->assertOk();

        $small = $this->coldCacheQueries();

        foreach (range(1, 4) as $unused) {
            $this->seedWorld();
        }
        $this->film(11);

        $this->assertGreaterThan(10, $small);
        $this->assertSame($small, $this->coldCacheQueries());
    }
}
