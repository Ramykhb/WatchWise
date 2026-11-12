<?php

namespace Tests\Feature\Admin;

use App\Admin\Mail\AccountReinstatedMail;
use App\Admin\Mail\AccountSuspendedMail;
use App\Admin\Support\AdminAction;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Mail;

/** A suspension's reason and end, the hourly job that lifts the ones that ended, and keeping both away from the member's own responses. */
class AdminSuspensionDetailsTest extends AdminTestCase
{
    /** Runs the command, then the emails it deferred (a command run from a test does not finish the way a real one does). */
    private function lift(int $expected): void
    {
        $this->artisan('admin:lift-suspensions')->expectsOutput("Reinstated {$expected} members whose suspension ended.")->assertSuccessful();
        app(DeferredCallbackCollection::class)->invoke();
    }

    private function suspended(array $attributes = []): User
    {
        $member = User::factory()->create(['is_active' => false]);
        $member->forceFill($attributes)->save();

        return $member;
    }

    /** @return array{string, string} the HTML and the plain text of the mail as the member reads them */
    private function rendered(object $mail): array
    {
        return (fn (): array => $this->renderForAssertions())->call($mail);
    }

    public function test_a_suspension_stores_its_reason_and_end_and_shows_them_in_the_email_and_the_log(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $member = User::factory()->create(['email' => 'maya@example.com']);
        $until = now()->addDays(10)->startOfMinute()->utc();

        $this->as($admin)->patchJson("/api/admin/users/{$member->id}", [
            'is_active' => false,
            'reason' => 'Repeated spam in reviews',
            'suspended_until' => $until->toIso8601String(),
        ])->assertOk()->assertJsonPath('user.is_active', false);

        $member->refresh();
        $this->assertSame('Repeated spam in reviews', $member->suspension_reason);
        $this->assertTrue($until->equalTo($member->suspended_until));

        Mail::assertSent(AccountSuspendedMail::class, 1);
        Mail::assertSent(AccountSuspendedMail::class, function (AccountSuspendedMail $mail) use ($admin, $until): bool {
            foreach ($this->rendered($mail) as $part) {
                if (! str_contains($part, 'Repeated spam in reviews') || ! str_contains($part, $until->format('j F Y, H:i').' UTC') || str_contains($part, $admin->email)) {
                    return false;
                }
            }

            return true;
        });

        $entry = AdminAuditLog::query()->sole();
        $this->assertSame(AdminAction::UserSuspended, $entry->action);
        $this->assertSame([
            'is_active' => ['from' => true, 'to' => false],
            'reason' => ['from' => null, 'to' => 'Repeated spam in reviews'],
            'until' => ['from' => null, 'to' => $until->toJSON()],
        ], $entry->changes);
    }

    public function test_a_plain_suspension_keeps_its_email_and_log_entry_as_before(): void
    {
        Mail::fake();
        $member = User::factory()->create();

        $this->as($this->admin())->patchJson("/api/admin/users/{$member->id}", ['is_active' => false])->assertOk();

        $this->assertNull($member->fresh()->suspension_reason);
        $this->assertNull($member->fresh()->suspended_until);
        $this->assertSame(['is_active' => ['from' => true, 'to' => false]], AdminAuditLog::query()->sole()->changes);
        Mail::assertSent(AccountSuspendedMail::class, function (AccountSuspendedMail $mail): bool {
            [$html, $text] = $this->rendered($mail);

            return ! str_contains($html.$text, 'Reason given') && str_contains($text, 'If the suspension is lifted, we’ll let you know by email.');
        });
    }

    public function test_the_reason_and_end_are_validated(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create();
        $url = "/api/admin/users/{$member->id}";
        $until = now()->addDays(5)->utc();

        $this->as($admin)->patchJson($url, ['is_active' => false, 'reason' => str_repeat('a', 256)])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->as($admin)->patchJson($url, ['is_active' => false, 'suspended_until' => now()->subDay()->toIso8601String()])->assertUnprocessable()->assertJsonValidationErrors('suspended_until');
        $this->as($admin)->patchJson($url, ['is_active' => false, 'suspended_until' => now()->addDays(366)->toIso8601String()])->assertUnprocessable()->assertJsonValidationErrors('suspended_until');
        $this->as($admin)->patchJson($url, ['is_active' => false, 'suspended_until' => 'next week'])->assertUnprocessable()->assertJsonValidationErrors('suspended_until');
        $this->as($admin)->patchJson($url, ['is_active' => true, 'reason' => 'Nope'])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->as($admin)->patchJson($url, ['role' => 'moderator', 'suspended_until' => $until->toIso8601String()])->assertUnprocessable()->assertJsonValidationErrors('suspended_until');
        $this->assertTrue($member->fresh()->is_active);

        $this->as($admin)->patchJson($url, ['is_active' => false, 'reason' => str_repeat('a', 255), 'suspended_until' => now()->addDays(364)->toIso8601String()])->assertOk();
    }

    public function test_reinstating_clears_the_reason_and_end_and_sends_a_plain_reinstated_email(): void
    {
        Mail::fake();
        $member = $this->suspended(['suspension_reason' => 'Spam', 'suspended_until' => now()->addDays(2)]);

        $this->as($this->admin())->patchJson("/api/admin/users/{$member->id}", ['is_active' => true])->assertOk();

        $member->refresh();
        $this->assertTrue($member->is_active);
        $this->assertNull($member->suspension_reason);
        $this->assertNull($member->suspended_until);
        $this->assertSame(['is_active' => ['from' => false, 'to' => true]], AdminAuditLog::query()->sole()->changes);
        Mail::assertSent(AccountReinstatedMail::class, 1);
    }

    public function test_suspending_an_already_suspended_member_keeps_the_first_reason(): void
    {
        Mail::fake();
        $member = $this->suspended(['suspension_reason' => 'First', 'suspended_until' => now()->addDay()]);

        $this->as($this->admin())->patchJson("/api/admin/users/{$member->id}", ['is_active' => false, 'reason' => 'Second'])->assertOk();

        $this->assertSame('First', $member->fresh()->suspension_reason);
        $this->assertDatabaseCount('admin_audit_logs', 0);
        Mail::assertNothingSent();
    }

    public function test_the_suspension_details_never_appear_in_member_facing_responses(): void
    {
        $member = User::factory()->create(['username' => 'maya']);
        $member->forceFill(['suspension_reason' => 'Hidden reason text', 'suspended_until' => now()->addDay()])->save();
        $viewer = User::factory()->create();

        foreach (['/api/me', '/api/users/maya', '/api/users?q=maya'] as $url) {
            $body = $this->as($member)->getJson($url)->getContent().$this->as($viewer)->getJson($url)->getContent();
            $this->assertStringNotContainsString('Hidden reason text', $body);
            $this->assertStringNotContainsString('suspension_reason', $body);
            $this->assertStringNotContainsString('suspended_until', $body);
        }

        $this->postJson('/api/auth/login', ['email' => $member->email, 'password' => 'password'])->assertOk();
        $this->assertArrayNotHasKey('suspension_reason', $member->fresh()->toArray());
        $this->assertArrayNotHasKey('suspended_until', $member->fresh()->toArray());
    }

    public function test_the_command_lifts_only_the_suspensions_that_ended_and_writes_a_system_entry(): void
    {
        Mail::fake();
        $endedAt = now()->subMinute()->startOfSecond()->utc();
        $ended = $this->suspended(['suspension_reason' => 'Spam', 'suspended_until' => $endedAt]);
        $endedExactly = $this->suspended(['suspended_until' => now()]);
        $future = $this->suspended(['suspension_reason' => 'Later', 'suspended_until' => now()->addHour()]);
        $indefinite = $this->suspended(['suspension_reason' => 'Open ended']);
        $active = User::factory()->create();

        $this->lift(2);

        foreach ([$ended, $endedExactly] as $member) {
            $member->refresh();
            $this->assertTrue($member->is_active);
            $this->assertNull($member->suspension_reason);
            $this->assertNull($member->suspended_until);
        }
        $this->assertFalse($future->fresh()->is_active);
        $this->assertSame('Later', $future->fresh()->suspension_reason);
        $this->assertFalse($indefinite->fresh()->is_active);
        $this->assertTrue($active->fresh()->is_active);

        $entries = AdminAuditLog::query()->orderBy('id')->get();
        $this->assertCount(2, $entries);
        $entry = $entries->first();
        $this->assertSame(AdminAction::UserSuspensionLifted, $entry->action);
        $this->assertNull($entry->actor_id);
        $this->assertSame('system', $entry->actor_role);
        $this->assertSame($ended->id, $entry->subject_id);
        $this->assertSame($ended->username, $entry->subject_label);
        $this->assertSame(['is_active' => ['from' => false, 'to' => true], 'until' => ['from' => $endedAt->toJSON(), 'to' => null]], $entry->changes);
        Mail::assertSent(AccountReinstatedMail::class, 2);
        Mail::assertSent(AccountReinstatedMail::class, fn (AccountReinstatedMail $mail): bool => $mail->hasTo($ended->email));
    }

    public function test_a_member_whose_suspension_ended_can_sign_in_only_once_the_command_has_run(): void
    {
        Mail::fake();
        $member = $this->suspended(['suspension_reason' => 'Spam', 'suspended_until' => now()->subMinute()]);
        $credentials = ['email' => $member->email, 'password' => 'password'];

        $this->postJson('/api/auth/login', $credentials)->assertUnauthorized();

        $this->lift(1);

        $this->postJson('/api/auth/login', $credentials)->assertOk();
    }

    public function test_running_the_command_again_changes_and_sends_nothing(): void
    {
        Mail::fake();
        $this->suspended(['suspended_until' => now()->subMinute()]);

        $this->lift(1);
        $this->lift(0);

        $this->assertDatabaseCount('admin_audit_logs', 1);
        Mail::assertSent(AccountReinstatedMail::class, 1);
    }

    public function test_a_member_staff_already_reinstated_is_not_lifted_again(): void
    {
        Mail::fake();
        $member = $this->suspended(['suspended_until' => now()->subMinute()]);
        $this->as($this->admin())->patchJson("/api/admin/users/{$member->id}", ['is_active' => true])->assertOk();

        $this->lift(0);

        $this->assertSame([AdminAction::UserReinstated], AdminAuditLog::query()->get()->map->action->all());
        Mail::assertSent(AccountReinstatedMail::class, 1);
    }

    public function test_a_failing_audit_insert_leaves_the_member_suspended_for_the_next_run(): void
    {
        Mail::fake();
        $member = $this->suspended(['suspension_reason' => 'Spam', 'suspended_until' => now()->subMinute()]);
        $this->failAuditInserts();

        $this->lift(0);

        $member->refresh();
        $this->assertFalse($member->is_active);
        $this->assertSame('Spam', $member->suspension_reason);
        Mail::assertNothingSent();
    }

    public function test_a_failing_audit_insert_rolls_back_a_suspension_with_details(): void
    {
        Mail::fake();
        $member = User::factory()->create();
        $until = now()->addDays(5);
        $this->failAuditInserts();

        $this->as($this->admin())->patchJson("/api/admin/users/{$member->id}", ['is_active' => false, 'reason' => 'Spam', 'suspended_until' => $until->toIso8601String()])->assertStatus(500);

        $member->refresh();
        $this->assertTrue($member->is_active);
        $this->assertNull($member->suspension_reason);
        $this->assertNull($member->suspended_until);
        Mail::assertNothingSent();
    }

    public function test_the_command_is_scheduled_hourly(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn (ScheduledEvent $event): bool => str_contains($event->command, 'admin:lift-suspensions'));

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}
