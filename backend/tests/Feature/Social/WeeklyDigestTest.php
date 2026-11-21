<?php

namespace Tests\Feature\Social;

use App\Digest\Mail\WeeklyDigestMail;
use App\Digest\Services\WeeklyDigestService;
use App\Models\Review;
use App\Models\User;
use App\Models\WeeklyDigestDelivery;
use App\Movie\DTOs\Responses\MoviePageResponse;
use App\Movie\DTOs\Responses\MovieResponse;
use App\Movie\InterfaceServices\MovieServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

class WeeklyDigestTest extends SocialTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-23 10:00:00', 'UTC'));
        Mail::fake();
        $this->mock(MovieServiceInterface::class, fn ($mock) => $mock->shouldReceive('trending')->andReturn(new MoviePageResponse(1, 1, 1, [
            new MovieResponse(42, 'A real catalogue pick', '', '2026-01-01', 2026, 4, 100, [], null, null),
        ])));
    }

    private function optedIn(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->preferences()->create(['settings' => ['integrations' => ['digest' => true]]]);

        return $user;
    }

    public function test_only_verified_active_opted_in_members_receive_one_digest_per_week(): void
    {
        $user = $this->optedIn();
        User::factory()->create();
        $this->optedIn(['is_active' => false]);
        $this->optedIn(['email_verified_at' => null]);
        $this->artisan('mail:weekly-digest')->assertSuccessful();
        $this->artisan('mail:weekly-digest')->assertSuccessful();
        Mail::assertSentCount(1);
        Mail::assertSent(WeeklyDigestMail::class, fn ($mail) => $mail->hasTo($user->email) && $mail->picks[0]['title'] === 'A real catalogue pick');
        $this->assertDatabaseCount('weekly_digest_deliveries', 1);
        $this->travel(7)->days();
        $this->artisan('mail:weekly-digest')->assertSuccessful();
        Mail::assertSentCount(2);
    }

    public function test_digest_uses_live_sharing_rules_and_excludes_spoilers_old_and_unapproved_activity(): void
    {
        $viewer = $this->optedIn();
        $visible = User::factory()->create();
        $hidden = User::factory()->private()->create();
        $privacy = User::factory()->create();
        $privacy->preferences()->create(['settings' => ['privacy' => ['reviews' => false, 'ratings' => false]]]);
        $viewer->following()->attach([$visible->id, $hidden->id, $privacy->id]);
        foreach ([$visible, $hidden, $privacy] as $index => $owner) {
            Review::create(['user_id' => $owner->id, 'tmdb_id' => 42 + $index, 'rating' => 5, 'content' => 'Secret spoiler', 'has_spoilers' => true, 'is_approved' => true]);
        }
        Review::create(['user_id' => $visible->id, 'tmdb_id' => 88, 'rating' => 4, 'content' => 'Old text', 'is_approved' => true])->forceFill(['updated_at' => now()->subDays(8)])->save();
        Review::create(['user_id' => $visible->id, 'tmdb_id' => 89, 'rating' => 2, 'content' => 'Unapproved', 'is_approved' => false]);
        $visible->preferences()->create(['settings' => ['privacy' => ['ratings' => false]]]);
        $this->artisan('mail:weekly-digest')->assertSuccessful();
        Mail::assertSent(WeeklyDigestMail::class, function ($mail) use ($visible): bool {
            $this->assertCount(1, $mail->activity);
            $this->assertSame($visible->name, $mail->activity[0]['name']);
            $this->assertNull($mail->activity[0]['rating']);
            $this->assertNull($mail->activity[0]['excerpt']);

            return true;
        });
    }

    public function test_failed_mail_remains_retryable_and_success_is_not_repeated(): void
    {
        $user = $this->optedIn();
        $mailer = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('Mail unavailable'));
        $this->assertSame(0, app(WeeklyDigestService::class)->sendDue());
        $delivery = WeeklyDigestDelivery::firstOrFail();
        $this->assertNull($delivery->sent_at);
        $this->assertSame(1, $delivery->attempts);
        Mail::swap($mailer);
        Mail::fake();
        $this->assertSame(1, app(WeeklyDigestService::class)->sendDue());
        $this->assertSame(0, app(WeeklyDigestService::class)->sendDue());
        Mail::assertSentCount(1);
        $this->assertNotNull($delivery->refresh()->sent_at);
    }

    public function test_opt_out_and_stale_deliveries_are_checked_again_before_sending(): void
    {
        $user = $this->optedIn();
        $delivery = WeeklyDigestDelivery::create(['user_id' => $user->id, 'week' => '2026-11-23']);
        $user->preferences()->update(['settings' => ['integrations' => ['digest' => false]]]);
        $this->assertFalse(app(WeeklyDigestService::class)->deliver($delivery->id));
        $this->assertNotNull($delivery->refresh()->skipped_at);
        $old = WeeklyDigestDelivery::create(['user_id' => $this->optedIn()->id, 'week' => '2026-11-16']);
        $this->assertFalse(app(WeeklyDigestService::class)->deliver($old->id));
        Mail::assertNothingSent();
    }

    public function test_delivery_starts_at_nine_utc_on_monday_and_renders_html_and_text(): void
    {
        $this->optedIn();
        $this->travelTo(CarbonImmutable::parse('2026-11-23 08:00:00', 'UTC'));
        $this->assertSame(0, app(WeeklyDigestService::class)->sendDue());
        Mail::assertNothingSent();
        $mail = new WeeklyDigestMail([['title' => '<script>Bad</script>', 'url' => 'https://example.test/movies/42']], [], 'https://example.test/home-page', 'https://example.test/settings');
        $mail->assertSeeInHtml('&lt;script&gt;Bad&lt;/script&gt;', false);
        $mail->assertSeeInHtml('Settings');
        $mail->assertSeeInText('No shared activity');
        $mail->assertSeeInText('https://example.test/settings');
    }
}
