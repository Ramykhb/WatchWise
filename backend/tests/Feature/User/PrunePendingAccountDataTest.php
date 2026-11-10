<?php

namespace Tests\Feature\User;

use App\Models\User;
use App\User\InterfaceRepository\AccountRepositoryInterface;
use App\User\InterfaceServices\AccountDeletionServiceInterface;
use App\User\InterfaceServices\AccountServiceInterface;
use App\User\InterfaceServices\EmailChangeServiceInterface;
use App\User\Mail\AuthorizeEmailChangeMail;
use App\User\Mail\ConfirmEmailChangeMail;
use App\User\Mail\EmailChangeRequestedMail;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Social\SocialTestCase;

class PrunePendingAccountDataTest extends SocialTestCase
{
    private const FRONT = 'http://front.test';

    private const EMAIL_MINUTES = EmailChangeServiceInterface::EXPIRES_MINUTES;

    private const DELETION_MINUTES = AccountDeletionServiceInterface::EXPIRES_MINUTES;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.frontend_url' => self::FRONT]);
        Mail::fake();
        Carbon::setTestNow(now()->startOfSecond());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function withPendingEmail(?int $minutesAgo, bool $authorized = false): User
    {
        $user = User::factory()->create();
        $user->forceFill([
            'pending_email' => 'new@example.com',
            'pending_email_nonce' => str_repeat('a', 64),
            'pending_email_authorized_at' => $authorized ? now()->subMinutes($minutesAgo ?? 0) : null,
            'pending_email_link_sent_at' => $minutesAgo === null ? null : now()->subMinutes($minutesAgo),
        ])->save();

        return $user;
    }

    private function withDeletionRequest(int $minutesAgo): User
    {
        $user = User::factory()->create();
        $user->forceFill([
            'deletion_requested_at' => now()->subMinutes($minutesAgo),
            'deletion_nonce' => str_repeat('b', 64),
        ])->save();

        return $user;
    }

    private function assertNothingPending(User $user): void
    {
        $user->refresh();
        $this->assertNull($user->pending_email);
        $this->assertNull($user->pending_email_nonce);
        $this->assertNull($user->pending_email_authorized_at);
        $this->assertNull($user->pending_email_link_sent_at);
    }

    /** Moves the last-link stamp back in time (a signed-in request cannot travel: the JWT is checked against the real clock). */
    private function ageStamp(User $user, int $minutes): void
    {
        User::whereKey($user->id)->update(['pending_email_link_sent_at' => now()->subMinutes($minutes)]);
    }

    private function requestChange(User $user, string $email = 'new@example.com'): void
    {
        $this->as($user)->putJson('/api/me/email', ['email' => $email, 'current_password' => 'password'])->assertOk();
    }

    public function test_an_expired_email_change_is_cleared(): void
    {
        $user = $this->withPendingEmail(self::EMAIL_MINUTES + 1, authorized: true);

        $this->artisan('users:prune-pending')->assertSuccessful();

        $this->assertNothingPending($user);
    }

    public function test_an_email_change_whose_link_is_still_valid_is_kept(): void
    {
        $user = $this->withPendingEmail(self::EMAIL_MINUTES - 1);

        $this->artisan('users:prune-pending')->assertSuccessful();

        $user->refresh();
        $this->assertSame('new@example.com', $user->pending_email);
        $this->assertNotNull($user->pending_email_nonce);
    }

    public function test_the_resend_endpoint_refreshes_the_stamp_so_an_old_change_survives_a_prune(): void
    {
        $user = User::factory()->create();
        $this->requestChange($user);
        $this->ageStamp($user, self::EMAIL_MINUTES + 30);

        $this->as($user)->postJson('/api/me/email/resend')->assertOk();
        $this->assertTrue($user->refresh()->pending_email_link_sent_at->equalTo(now()));

        $this->artisan('users:prune-pending')->assertSuccessful();
        $this->assertSame('new@example.com', $user->refresh()->pending_email);

        $this->ageStamp($user, self::EMAIL_MINUTES + 1);
        $this->artisan('users:prune-pending')->assertSuccessful();
        $this->assertNothingPending($user);
    }

    public function test_an_approved_change_survives_a_prune_while_its_new_link_is_valid(): void
    {
        $user = User::factory()->withoutPassword()->create();
        $this->as($user)->putJson('/api/me/email', ['email' => 'new@example.com'])->assertOk();
        $authorizeUrl = Mail::sent(AuthorizeEmailChangeMail::class)->last()->url;

        $this->travel(self::EMAIL_MINUTES - 1)->minutes();
        $this->get($authorizeUrl)->assertRedirect(self::FRONT.'/edit-profile?email_change=check-new-inbox');
        Mail::assertSent(ConfirmEmailChangeMail::class, 1);

        // Minute 61 after the request: the approval link is gone, the confirmation link sent at 59 is not.
        $this->travel(2)->minutes();
        $this->artisan('users:prune-pending')->assertSuccessful();

        $user->refresh();
        $this->assertSame('new@example.com', $user->pending_email);
        $this->assertNotNull($user->pending_email_authorized_at);
    }

    public function test_a_pending_address_without_a_stamp_gets_a_grace_window_before_it_is_cleared(): void
    {
        $user = $this->withPendingEmail(null);

        $this->artisan('users:prune-pending')
            ->expectsOutput('Cleared 0 expired pending email changes.')
            ->assertSuccessful();

        $user->refresh();
        $this->assertSame('new@example.com', $user->pending_email);
        $this->assertTrue($user->pending_email_link_sent_at->equalTo(now()));

        $this->travel(self::EMAIL_MINUTES + 1)->minutes();
        $this->artisan('users:prune-pending')
            ->expectsOutput('Cleared 1 expired pending email changes.')
            ->assertSuccessful();
        $this->assertNothingPending($user);
    }

    public function test_a_nonce_left_without_an_address_is_cleared(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['pending_email_nonce' => str_repeat('c', 64)])->save();

        $this->artisan('users:prune-pending')->assertSuccessful();

        $this->assertNothingPending($user);
    }

    public function test_cancel_apply_and_password_changes_null_the_stamp(): void
    {
        $cancelled = User::factory()->create();
        $this->requestChange($cancelled);
        $this->assertNotNull($cancelled->refresh()->pending_email_link_sent_at);
        $this->as($cancelled)->deleteJson('/api/me/email')->assertOk();
        $this->assertNothingPending($cancelled);

        $applied = User::factory()->create();
        $this->requestChange($applied, 'applied@example.com');
        $this->get(Mail::sent(ConfirmEmailChangeMail::class)->last()->url)
            ->assertRedirect(self::FRONT.'/edit-profile?email_change=success');
        $this->assertNothingPending($applied);
        $this->assertSame('applied@example.com', $applied->email);

        $changed = User::factory()->create();
        $this->requestChange($changed, 'changed@example.com');
        $this->as($changed)->putJson('/api/me/password', [
            'current_password' => 'password', 'password' => 'brand-new-pass1', 'password_confirmation' => 'brand-new-pass1',
        ])->assertOk();
        $this->assertNothingPending($changed);

        $reset = User::factory()->create();
        $this->requestChange($reset, 'reset@example.com');
        app(AccountServiceInterface::class)->passwordResetThroughEmail($reset->refresh());
        $this->assertNothingPending($reset);
    }

    public function test_the_cancel_link_after_pruning_is_a_calm_no_op(): void
    {
        $user = User::factory()->create();
        $this->requestChange($user);
        $cancelUrl = Mail::sent(EmailChangeRequestedMail::class)->last()->cancelUrl;

        $this->travel(self::EMAIL_MINUTES + 1)->minutes();
        $this->artisan('users:prune-pending')->assertSuccessful();

        $this->get($cancelUrl)->assertRedirect(self::FRONT.'/edit-profile?email_change=expired');
    }

    public function test_a_resend_after_pruning_is_refused_and_mails_nothing(): void
    {
        $user = User::factory()->create();
        $this->requestChange($user);
        Mail::assertSent(ConfirmEmailChangeMail::class, 1);

        $this->ageStamp($user, self::EMAIL_MINUTES + 1);
        $this->artisan('users:prune-pending')->assertSuccessful();

        $this->as($user)->postJson('/api/me/email/resend')->assertUnprocessable()->assertJsonValidationErrors('email');
        Mail::assertSent(ConfirmEmailChangeMail::class, 1);
    }

    public function test_marking_a_link_sent_fails_when_the_change_was_replaced_meanwhile(): void
    {
        $user = $this->withPendingEmail(1);
        $stale = User::find($user->id);
        $user->forceFill(['pending_email_nonce' => str_repeat('z', 64)])->save();

        $this->assertFalse(app(AccountRepositoryInterface::class)->markPendingEmailLinkSent($stale));
    }

    public function test_an_expired_deletion_request_is_cleared(): void
    {
        $user = $this->withDeletionRequest(self::DELETION_MINUTES + 1);

        $this->artisan('users:prune-pending')->assertSuccessful();

        $user->refresh();
        $this->assertNull($user->deletion_requested_at);
        $this->assertNull($user->deletion_nonce);
    }

    public function test_a_deletion_request_whose_link_is_still_valid_is_kept(): void
    {
        $user = $this->withDeletionRequest(self::DELETION_MINUTES - 1);

        $this->artisan('users:prune-pending')->assertSuccessful();

        $user->refresh();
        $this->assertNotNull($user->deletion_requested_at);
        $this->assertNotNull($user->deletion_nonce);
    }

    public function test_accounts_without_pending_data_are_untouched(): void
    {
        $user = User::factory()->create(['email' => 'plain@example.com']);
        $before = $user->fresh()->getAttributes();

        $this->artisan('users:prune-pending')->assertSuccessful();

        $this->assertEquals($before, $user->fresh()->getAttributes());
    }

    public function test_expiry_follows_time_passing(): void
    {
        $user = $this->withDeletionRequest(0);

        $this->artisan('users:prune-pending')->assertSuccessful();
        $this->assertNotNull($user->refresh()->deletion_nonce);

        $this->travel(self::DELETION_MINUTES + 1)->minutes();

        $this->artisan('users:prune-pending')->assertSuccessful();
        $this->assertNull($user->refresh()->deletion_nonce);
    }

    public function test_the_command_reports_how_many_rows_were_cleared_for_each_kind(): void
    {
        $this->withPendingEmail(self::EMAIL_MINUTES + 5);
        $this->withPendingEmail(self::EMAIL_MINUTES + 5);
        $this->withPendingEmail(1);
        $this->withDeletionRequest(self::DELETION_MINUTES + 5);
        $this->withDeletionRequest(1);

        $this->artisan('users:prune-pending')
            ->expectsOutput('Cleared 2 expired pending email changes.')
            ->expectsOutput('Cleared 1 expired account deletion requests.')
            ->assertSuccessful();
    }

    public function test_the_command_is_scheduled_hourly_without_overlapping(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'users:prune-pending'));

        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
    }
}
