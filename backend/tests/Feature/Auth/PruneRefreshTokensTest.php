<?php

namespace Tests\Feature\Auth;

use App\Models\RefreshToken;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** `auth:prune-refresh-tokens` deletes tokens expired or revoked longer ago than the retention, and keeps the rest. */
class PruneRefreshTokensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.refresh_prune_retention_days' => 7, 'jwt.refresh_reuse_grace_seconds' => 20]);
        Carbon::setTestNow(now()->startOfSecond());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_deletes_tokens_expired_or_revoked_before_the_retention_and_keeps_the_rest(): void
    {
        $expiredLongAgo = RefreshToken::factory()->create(['expires_at' => now()->subDays(8)]);
        $revokedLongAgo = RefreshToken::factory()->create(['revoked_at' => now()->subDays(8)]);
        $expiredRecently = RefreshToken::factory()->create(['expires_at' => now()->subDays(6)]);
        $revokedRecently = RefreshToken::factory()->create(['revoked_at' => now()->subDays(6)]);
        $live = RefreshToken::factory()->create();

        $this->artisan('auth:prune-refresh-tokens')
            ->expectsOutput('Deleted 2 stale refresh tokens.')
            ->assertSuccessful();

        $this->assertModelMissing($expiredLongAgo);
        $this->assertModelMissing($revokedLongAgo);
        $this->assertModelExists($expiredRecently);
        $this->assertModelExists($revokedRecently);
        $this->assertModelExists($live);
    }

    public function test_a_rotated_token_is_kept_until_it_expired_longer_ago_than_the_retention(): void
    {
        $successor = RefreshToken::factory()->create();
        $rotatedLongAgo = RefreshToken::factory()->create(['revoked_at' => now()->subDays(20), 'replaced_by' => $successor->id, 'expires_at' => now()->addDays(10)]);
        $rotatedAndExpiredRecently = RefreshToken::factory()->create(['revoked_at' => now()->subDays(40), 'replaced_by' => $successor->id, 'expires_at' => now()->subDays(6)]);
        $rotatedAndExpiredLongAgo = RefreshToken::factory()->create(['revoked_at' => now()->subDays(40), 'replaced_by' => $successor->id, 'expires_at' => now()->subDays(8)]);

        $this->artisan('auth:prune-refresh-tokens')->expectsOutput('Deleted 1 stale refresh tokens.')->assertSuccessful();

        $this->assertModelExists($rotatedLongAgo);
        $this->assertModelExists($rotatedAndExpiredRecently);
        $this->assertModelMissing($rotatedAndExpiredLongAgo);
    }

    public function test_the_retention_never_drops_below_the_reuse_grace_window(): void
    {
        config(['jwt.refresh_prune_retention_days' => 0, 'jwt.refresh_reuse_grace_seconds' => 20]);
        $insideGrace = RefreshToken::factory()->create(['revoked_at' => now()->subSeconds(10)]);
        $pastGrace = RefreshToken::factory()->create(['revoked_at' => now()->subSeconds(30)]);

        $this->artisan('auth:prune-refresh-tokens')->expectsOutput('Deleted 1 stale refresh tokens.')->assertSuccessful();

        $this->assertModelExists($insideGrace);
        $this->assertModelMissing($pastGrace);
    }

    public function test_the_command_is_scheduled_hourly_without_overlapping(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'auth:prune-refresh-tokens'));

        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
    }
}
