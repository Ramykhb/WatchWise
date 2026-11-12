<?php

namespace Tests\Feature\Admin;

use App\Admin\Mail\AccountReinstatedMail;
use App\Admin\Mail\AccountSuspendedMail;
use App\Models\Notification;
use App\Models\Review;
use App\Models\User;
use App\Notification\InterfaceServices\NotificationServiceInterface;
use App\Support\Exceptions\MailNotDelivered;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;
use Mockery\MockInterface;
use RuntimeException;

/** What staff actions tell the affected member: an email for a suspension or reinstatement, a notification for a removed review. */
class AdminMemberNoticesTest extends AdminTestCase
{
    private const REVIEW_TEXT = 'A secret opinion nobody else may read.';

    private function moderator(): User
    {
        return User::factory()->create(['role' => 'moderator']);
    }

    /** @return list<array{type: string, data: array<string, mixed>}> */
    private function noticesOf(User $member): array
    {
        return Notification::where('user_id', $member->id)->orderBy('id')->get(['type', 'data'])
            ->map(fn (Notification $notification): array => ['type' => $notification->type, 'data' => $notification->data])
            ->all();
    }

    private function brevoMailerThatIsDown(): void
    {
        config([
            'mail.default' => 'brevo',
            'mail.mailers.brevo.key' => 'test-brevo-key',
            'mail.from.address' => 'sender@example.com',
            'mail.from.name' => 'WatchWise',
        ]);
        Http::preventStrayRequests();
        Http::fake(['api.brevo.com/v3/smtp/email' => Http::response(['message' => 'down'], 503)]);
    }

    public function test_suspending_a_member_emails_them_once_without_naming_who_acted(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create(['name' => 'Alexandra Staffer', 'username' => 'alexstaff', 'email' => 'staff@example.com']);
        $member = User::factory()->create(['email' => 'member@example.com', 'name' => 'Mia Member']);

        $this->as($admin)->patchJson("/api/admin/users/{$member->id}", ['is_active' => false])->assertOk();

        Mail::assertSent(AccountSuspendedMail::class, 1);
        Mail::assertSent(AccountSuspendedMail::class, function (AccountSuspendedMail $mail): bool {
            [$html, $text] = (fn (): array => $this->renderForAssertions())->call($mail);

            foreach ([$html, $text] as $part) {
                if (! str_contains($part, 'Mia Member') || ! str_contains($part, 'suspended by the WatchWise team')) {
                    return false;
                }

                foreach (['Alexandra', 'alexstaff', 'staff@example.com', 'member@example.com'] as $leak) {
                    if (str_contains($part, $leak)) {
                        return false;
                    }
                }
            }

            return $mail->hasTo('member@example.com') && $mail->hasSubject('Your WatchWise account was suspended');
        });
        Mail::assertNotSent(AccountReinstatedMail::class);
    }

    public function test_reinstating_a_member_emails_them_once_with_a_sign_in_link(): void
    {
        config(['services.frontend_url' => 'https://front.test']);
        Mail::fake();
        $member = User::factory()->create(['is_active' => false, 'email' => 'member@example.com']);

        $this->as($this->moderator())->patchJson("/api/admin/users/{$member->id}", ['is_active' => true])->assertOk();

        Mail::assertSent(AccountReinstatedMail::class, 1);
        Mail::assertSent(AccountReinstatedMail::class, function (AccountReinstatedMail $mail): bool {
            [$html, $text] = (fn (): array => $this->renderForAssertions())->call($mail);

            return $mail->hasTo('member@example.com')
                && str_contains($html, 'https://front.test/signin')
                && str_contains($text, 'https://front.test/signin');
        });
        Mail::assertNotSent(AccountSuspendedMail::class);
    }

    public function test_the_email_goes_to_the_stored_address_even_when_it_is_unverified(): void
    {
        Mail::fake();
        $member = User::factory()->unverified()->create(['email' => 'unverified@example.com']);

        $this->as($this->admin())->patchJson("/api/admin/users/{$member->id}", ['is_active' => false])->assertOk();

        Mail::assertSent(AccountSuspendedMail::class, fn (AccountSuspendedMail $mail): bool => $mail->hasTo('unverified@example.com'));
    }

    public function test_a_suspension_that_also_changes_the_role_sends_one_email(): void
    {
        Mail::fake();
        $member = User::factory()->create();

        $this->as($this->admin())->patchJson("/api/admin/users/{$member->id}", ['role' => 'moderator', 'is_active' => false])->assertOk();

        Mail::assertSent(AccountSuspendedMail::class, 1);
        Mail::assertSentCount(1);
    }

    public function test_updates_that_do_not_change_the_active_state_send_nothing(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $active = User::factory()->create();
        $suspended = User::factory()->create(['is_active' => false]);

        $this->as($admin)->patchJson("/api/admin/users/{$active->id}", ['is_active' => true])->assertOk();
        $this->as($admin)->patchJson("/api/admin/users/{$suspended->id}", ['is_active' => false])->assertOk();

        Mail::assertNothingSent();
    }

    public function test_a_role_change_sends_nothing(): void
    {
        Mail::fake();
        $member = User::factory()->create();

        $this->as($this->admin())->patchJson("/api/admin/users/{$member->id}", ['role' => 'moderator'])->assertOk();
        $this->as($this->admin())->patchJson("/api/admin/users/{$member->id}", ['role' => 'user'])->assertOk();

        Mail::assertNothingSent();
    }

    public function test_refused_and_invalid_updates_send_nothing(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $moderator = $this->moderator();
        $otherModerator = $this->moderator();
        $member = User::factory()->create();

        $this->as($admin)->patchJson("/api/admin/users/{$admin->id}", ['is_active' => false])->assertForbidden();
        $this->as($moderator)->patchJson("/api/admin/users/{$moderator->id}", ['is_active' => false])->assertForbidden();
        $this->as($moderator)->patchJson("/api/admin/users/{$otherModerator->id}", ['is_active' => false])->assertForbidden();
        $this->as($moderator)->patchJson("/api/admin/users/{$admin->id}", ['is_active' => false])->assertForbidden();
        $this->as($moderator)->patchJson("/api/admin/users/{$member->id}", ['role' => 'admin', 'is_active' => false])->assertForbidden();
        $this->as($admin)->patchJson("/api/admin/users/{$member->id}", ['is_active' => 'maybe'])->assertUnprocessable();
        $this->as($admin)->patchJson('/api/admin/users/9999', ['is_active' => false])->assertNotFound();
        $this->as($member)->patchJson("/api/admin/users/{$member->id}", ['is_active' => false])->assertForbidden();

        Mail::assertNothingSent();
        $this->assertTrue($member->fresh()->is_active);
    }

    public function test_a_bulk_suspension_emails_each_member_once(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $members = User::factory()->count(3)->create();

        foreach ($members as $member) {
            $this->as($admin)->patchJson("/api/admin/users/{$member->id}", ['is_active' => false])->assertOk();
        }

        Mail::assertSent(AccountSuspendedMail::class, 3);
        foreach ($members as $member) {
            Mail::assertSent(AccountSuspendedMail::class, fn (AccountSuspendedMail $mail): bool => $mail->hasTo($member->email));
        }
    }

    public function test_a_failing_audit_insert_sends_no_email_and_keeps_the_member_active(): void
    {
        Mail::fake();
        $member = User::factory()->create();
        $suspended = User::factory()->create(['is_active' => false]);
        $this->failAuditInserts();

        $this->as($this->admin())->patchJson("/api/admin/users/{$member->id}", ['is_active' => false])->assertStatus(500);
        $this->as($this->admin())->patchJson("/api/admin/users/{$suspended->id}", ['is_active' => true])->assertStatus(500);

        Mail::assertNothingSent();
        $this->assertTrue($member->fresh()->is_active);
        $this->assertFalse($suspended->fresh()->is_active);
    }

    public function test_a_failing_mail_transport_does_not_fail_or_undo_the_suspension(): void
    {
        Sleep::fake();
        Exceptions::fake();
        $this->brevoMailerThatIsDown();
        $member = User::factory()->create(['email' => 'member@example.com']);

        $this->as($this->admin())->patchJson("/api/admin/users/{$member->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('user.is_active', false);

        $this->assertFalse($member->fresh()->is_active);
        $this->assertDatabaseCount('admin_audit_logs', 1);
        Http::assertSentCount(2);
        Exceptions::assertReported(function (MailNotDelivered $e): bool {
            return $e->getMessage() === 'AccountSuspendedMail was not delivered after 2 attempt(s): TransportException.'
                && ! str_contains($e->getMessage(), 'member@example.com');
        });
    }

    public function test_rejecting_a_review_tells_its_author_only_the_film(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Alexandra Staffer', 'username' => 'alexstaff']);
        $review = $this->review(['content' => self::REVIEW_TEXT, 'media_type' => 'movie', 'tmdb_id' => 550]);
        $author = $review->user;

        $this->as($admin)->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'rejected'])->assertOk();

        $this->assertSame(
            [['type' => Notification::REVIEW_REMOVED, 'data' => ['tmdb_id' => 550, 'media_type' => 'movie', 'action' => 'removed']]],
            $this->noticesOf($author),
        );
        $this->assertStringNotContainsString(self::REVIEW_TEXT, json_encode(Notification::where('user_id', $author->id)->value('data')));
        $this->assertSame(1, Notification::count());
    }

    public function test_deleting_a_review_tells_its_author_with_the_shows_media_type(): void
    {
        $review = $this->review(['content' => self::REVIEW_TEXT, 'media_type' => 'tv', 'tmdb_id' => 1396]);
        $author = $review->user;

        $this->as($this->moderator())->deleteJson("/api/admin/reviews/{$review->id}")->assertOk();

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertSame(
            [['type' => Notification::REVIEW_REMOVED, 'data' => ['tmdb_id' => 1396, 'media_type' => 'tv', 'action' => 'removed']]],
            $this->noticesOf($author),
        );
    }

    public function test_the_author_sees_the_notice_as_from_the_watchwise_team_whatever_their_settings(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Alexandra Staffer', 'username' => 'alexstaff']);
        $review = $this->review(['content' => self::REVIEW_TEXT, 'media_type' => 'movie', 'tmdb_id' => 550]);
        $author = $review->user;
        $this->as($author)->patchJson('/api/me/preferences', ['notifications' => ['review_helpful' => false, 'follow' => false, 'review_removed' => false]])->assertOk();

        $this->as($admin)->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'rejected'])->assertOk();

        $feed = $this->as($author)->getJson('/api/notifications')
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.kind', 'review_removed')
            ->assertJsonPath('notifications.0.actor', ['id' => 0, 'username' => 'watchwise', 'name' => 'WatchWise', 'avatar' => null])
            ->assertJsonPath('notifications.0.text', 'removed your review')
            ->assertJsonPath('notifications.0.film', ['tmdb_id' => 550, 'media_type' => 'movie'])
            ->assertJsonPath('notifications.0.to', '/movies/550')
            ->assertJsonPath('notifications.0.read', false);
        $this->assertStringNotContainsString('alexstaff', $feed->getContent());
        $this->assertStringNotContainsString(self::REVIEW_TEXT, $feed->getContent());
        $this->as($author)->getJson('/api/me')->assertJsonPath('counts.unread_notifications', 1);
    }

    public function test_approving_a_review_tells_nobody(): void
    {
        $review = $this->review(['content' => self::REVIEW_TEXT]);

        $this->as($this->admin())->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'approved'])->assertOk();

        $this->assertSame(0, Notification::count());
    }

    public function test_a_review_without_text_or_already_removed_tells_nobody_again(): void
    {
        $admin = $this->admin();
        $rating = $this->review(['content' => null, 'sentiment' => null]);
        $review = $this->review(['content' => self::REVIEW_TEXT]);

        $this->as($admin)->deleteJson("/api/admin/reviews/{$rating->id}")->assertOk();
        $this->as($admin)->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'rejected'])->assertOk();
        $this->as($admin)->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'rejected'])->assertOk();
        $this->as($admin)->deleteJson("/api/admin/reviews/{$review->id}")->assertOk();

        $this->assertSame([Notification::REVIEW_REMOVED], Notification::pluck('type')->all());
    }

    public function test_a_deleted_author_leaves_no_review_to_moderate_and_nobody_to_tell(): void
    {
        $admin = $this->admin();
        $review = $this->review(['content' => self::REVIEW_TEXT]);
        $review->user->delete();

        $this->as($admin)->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'rejected'])->assertNotFound();
        $this->as($admin)->deleteJson("/api/admin/reviews/{$review->id}")->assertNotFound();

        $this->assertSame(0, Notification::count());
    }

    public function test_a_failing_audit_insert_tells_nobody(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $review = $this->review(['content' => self::REVIEW_TEXT]);
        $this->failAuditInserts();

        $this->as($admin)->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'rejected'])->assertStatus(500);
        $this->as($admin)->deleteJson("/api/admin/reviews/{$review->id}")->assertStatus(500);

        $this->assertSame(0, Notification::count());
        $this->assertSame(self::REVIEW_TEXT, Review::findOrFail($review->id)->content);
    }

    public function test_a_failing_notification_does_not_fail_or_undo_the_moderation(): void
    {
        Exceptions::fake();
        $admin = $this->admin();
        $rejected = $this->review(['content' => self::REVIEW_TEXT]);
        $deleted = $this->review(['content' => self::REVIEW_TEXT, 'tmdb_id' => 551]);
        $this->mock(NotificationServiceInterface::class, function (MockInterface $notifications): void {
            $notifications->shouldReceive('reviewRemoved')->twice()->andThrow(new RuntimeException('The notification store is unavailable.'));
        });

        $this->as($admin)->patchJson("/api/admin/reviews/{$rejected->id}", ['status' => 'rejected'])->assertOk()->assertJsonPath('review.status', 'removed');
        $this->as($admin)->deleteJson("/api/admin/reviews/{$deleted->id}")->assertOk();

        $this->assertNotNull(Review::findOrFail($rejected->id)->removed_at);
        $this->assertDatabaseMissing('reviews', ['id' => $deleted->id]);
        $this->assertDatabaseCount('admin_audit_logs', 2);
        Exceptions::assertReportedCount(2);
    }

    public function test_a_review_notice_never_sends_email(): void
    {
        Mail::fake();
        $review = $this->review(['content' => self::REVIEW_TEXT]);

        $this->as($this->admin())->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'rejected'])->assertOk();

        Mail::assertNothingSent();
    }
}
