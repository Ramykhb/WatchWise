<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The migration that adds `refresh_tokens.family_started_at` backfills it with the start of each family. */
class FamilyStartedAtMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(now()->startOfSecond());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_11_120001_add_family_started_at_to_refresh_tokens_table.php');
    }

    private function insertToken(int $userId, string $family, ?Carbon $createdAt): void
    {
        DB::table('refresh_tokens')->insert([
            'user_id' => $userId, 'token_hash' => bin2hex(random_bytes(32)), 'family_id' => $family,
            'expires_at' => now()->addDays(30), 'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);
    }

    public function test_existing_rows_get_the_earliest_creation_time_of_their_family(): void
    {
        $migration = $this->migration();
        $migration->down();
        $user = User::factory()->create();

        $this->insertToken($user->id, 'a', now()->subDays(40));
        $this->insertToken($user->id, 'a', now()->subDays(10));
        $this->insertToken($user->id, 'b', now()->subDays(3));
        $this->insertToken($user->id, 'c', null);

        $migration->up();

        $startedAt = DB::table('refresh_tokens')->orderBy('id')->pluck('family_started_at')
            ->map(fn (?string $value): ?string => $value === null ? null : Carbon::parse($value)->toDateTimeString())
            ->all();

        $this->assertSame([
            now()->subDays(40)->toDateTimeString(),
            now()->subDays(40)->toDateTimeString(),
            now()->subDays(3)->toDateTimeString(),
            now()->toDateTimeString(),
        ], $startedAt);
    }
}
