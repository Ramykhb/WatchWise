<?php

namespace Tests\Feature\Admin;

use App\Admin\Support\AdminAction;
use App\Auth\Mail\VerifyEmailMail;
use App\Models\AdminAuditLog;
use App\Models\Notification;
use App\Models\RefreshToken;
use App\Models\Report;
use App\Models\User;
use App\Notification\InterfaceServices\NotificationServiceInterface;
use App\Report\Support\ReportTargetType;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;

/** Signing a member out, cleaning up their profile and resending their verification email. */
class AdminUserToolsTest extends AdminTestCase
{
    private function moderator(): User
    {
        return User::factory()->create(['role' => 'moderator']);
    }

    private function withAvatarFile(array $attributes = []): User
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/maya.png', 'image');
        $member = User::factory()->create($attributes);
        $member->forceFill(['avatar' => 'avatars/maya.png'])->save();

        return $member;
    }

    /** @return array<string, array{string, string}> */
    public static function actions(): array
    {
        return [
            'sign out' => ['post', 'sign-out'],
            'remove avatar' => ['delete', 'avatar'],
            'remove bio' => ['delete', 'bio'],
            'resend verification' => ['post', 'resend-verification'],
        ];
    }

    #[DataProvider('actions')]
    public function test_the_permission_matrix(string $method, string $path): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $moderator = $this->moderator();
        $colleague = $this->moderator();
        $call = fn (User $by, User $on) => $this->as($by)->json($method, "/api/admin/users/{$on->id}/{$path}");
        $target = fn () => User::factory()->unverified()->create(['bio' => 'Bio', 'avatar' => 'https://example.com/a.png']);

        $this->json($method, "/api/admin/users/{$target()->id}/{$path}")->assertUnauthorized();
        $call(User::factory()->create(), $target())->assertForbidden();

        $call($moderator, $admin)->assertForbidden();
        $call($moderator, $colleague)->assertForbidden();
        $call($moderator, $moderator)->assertForbidden();
        $call($admin, $admin)->assertForbidden();

        $call($moderator, $target())->assertOk();
        $call($admin, $target())->assertOk();
        $call($admin, $target())->assertOk();
        $this->assertDatabaseMissing('admin_audit_logs', ['subject_id' => $admin->id]);
    }

    public function test_signing_out_revokes_every_session_and_is_recorded_without_telling_the_member(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $member = User::factory()->create(['username' => 'maya']);
        RefreshToken::factory()->count(2)->create(['user_id' => $member->id]);
        $oldAccessVersion = $member->fresh()->session_version;

        $this->as($admin)->postJson("/api/admin/users/{$member->id}/sign-out")->assertOk();

        $this->assertSame(0, RefreshToken::query()->where('user_id', $member->id)->whereNull('revoked_at')->count());
        $this->assertGreaterThan($oldAccessVersion, $member->fresh()->session_version);
        $this->assertTrue($member->fresh()->is_active);
        $entry = AdminAuditLog::query()->sole();
        $this->assertSame(AdminAction::UserSignedOut, $entry->action);
        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertSame($member->id, $entry->subject_id);
        $this->assertSame('maya', $entry->subject_label);
        Mail::assertNothingSent();
        $this->assertSame(0, Notification::query()->where('user_id', $member->id)->count());
    }

    public function test_a_failing_audit_insert_keeps_the_sessions(): void
    {
        $member = User::factory()->create();
        RefreshToken::factory()->create(['user_id' => $member->id]);
        $this->failAuditInserts();

        $this->as($this->admin())->postJson("/api/admin/users/{$member->id}/sign-out")->assertStatus(500);

        $this->assertSame(1, RefreshToken::query()->where('user_id', $member->id)->whereNull('revoked_at')->count());
    }

    public function test_removing_an_uploaded_avatar_deletes_the_file_audits_and_tells_the_member(): void
    {
        $admin = $this->admin();
        $member = $this->withAvatarFile(['username' => 'maya']);
        $report = Report::factory()->forTarget(ReportTargetType::Profile, $member)->create();

        $this->as($admin)->deleteJson("/api/admin/users/{$member->id}/avatar")->assertOk();

        $this->assertNull($member->fresh()->avatar);
        Storage::disk('public')->assertMissing('avatars/maya.png');
        $entry = AdminAuditLog::query()->sole();
        $this->assertSame(AdminAction::UserAvatarRemoved, $entry->action);
        $this->assertSame($member->id, $entry->subject_id);
        $this->assertSame('maya', $entry->subject_label);
        $this->assertSame([['type' => Notification::PROFILE_CHANGED, 'data' => ['action' => 'avatar_removed']]], $this->noticesOf($member));
        $this->assertNull($report->fresh()->resolved_at, 'Reports are left for staff to close.');

        $this->as($admin)->deleteJson("/api/admin/users/{$member->id}/avatar")->assertNotFound();
        $this->assertDatabaseCount('admin_audit_logs', 1);
        $this->assertCount(1, $this->noticesOf($member));
    }

    public function test_removing_an_external_avatar_only_forgets_the_url(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/other.png', 'image');
        $member = User::factory()->create(['avatar' => 'https://lh3.googleusercontent.com/a.png']);

        $this->as($this->admin())->deleteJson("/api/admin/users/{$member->id}/avatar")->assertOk();

        $this->assertNull($member->fresh()->avatar);
        Storage::disk('public')->assertExists('avatars/other.png');
        $this->assertSame(AdminAction::UserAvatarRemoved, AdminAuditLog::query()->sole()->action);
    }

    public function test_removing_a_missing_avatar_or_bio_is_a_404_that_records_and_sends_nothing(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['bio' => '   ']);

        $this->as($admin)->deleteJson("/api/admin/users/{$member->id}/avatar")->assertNotFound();
        $this->as($admin)->deleteJson("/api/admin/users/{$member->id}/bio")->assertNotFound();

        $this->assertDatabaseCount('admin_audit_logs', 0);
        $this->assertSame([], $this->noticesOf($member));
    }

    public function test_removing_the_bio_empties_it_audits_and_tells_the_member_without_the_text(): void
    {
        $member = User::factory()->create(['username' => 'maya', 'bio' => 'Buy cheap watches at example.com']);
        $report = Report::factory()->forTarget(ReportTargetType::Profile, $member)->create();

        $this->as($this->moderator())->deleteJson("/api/admin/users/{$member->id}/bio")->assertOk();

        $this->assertNull($member->fresh()->bio);
        $entry = AdminAuditLog::query()->sole();
        $this->assertSame(AdminAction::UserBioRemoved, $entry->action);
        $this->assertSame('moderator', $entry->actor_role);
        $this->assertNull($entry->changes);
        $this->assertSame([['type' => Notification::PROFILE_CHANGED, 'data' => ['action' => 'bio_removed']]], $this->noticesOf($member));
        $this->assertNull($report->fresh()->resolved_at);
    }

    public function test_the_member_sees_the_notice_as_from_the_team_even_with_every_switch_off(): void
    {
        $member = User::factory()->create(['bio' => 'Bio']);
        $this->as($member)->patchJson('/api/me/preferences', ['notifications' => array_fill_keys(Notification::OPTIONAL_TYPES, false)])->assertOk();

        $this->as($this->admin())->deleteJson("/api/admin/users/{$member->id}/bio")->assertOk();

        $this->as($member)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('notifications.0.kind', 'profile_changed')
            ->assertJsonPath('notifications.0.actor.id', 0)
            ->assertJsonPath('notifications.0.actor.username', 'watchwise')
            ->assertJsonPath('notifications.0.text', 'removed your bio');
        $this->as($member)->getJson('/api/me')->assertJsonPath('counts.unread_notifications', 1);
        $this->assertContains(Notification::PROFILE_CHANGED, Notification::FEED_TYPES);
        $this->assertNotContains(Notification::PROFILE_CHANGED, Notification::OPTIONAL_TYPES);
    }

    public function test_a_failing_notification_never_undoes_the_removal(): void
    {
        $member = User::factory()->create(['bio' => 'Bio']);
        $this->mock(NotificationServiceInterface::class, function ($notifications): void {
            $notifications->shouldReceive('profileChanged')->once()->andThrow(new \RuntimeException('The notification store is unavailable.'));
        });

        $this->as($this->admin())->deleteJson("/api/admin/users/{$member->id}/bio")->assertOk();

        $this->assertNull($member->fresh()->bio);
        $this->assertSame(AdminAction::UserBioRemoved, AdminAuditLog::query()->sole()->action);
    }

    public function test_a_failing_audit_insert_keeps_the_avatar_file_and_the_bio(): void
    {
        $member = $this->withAvatarFile(['bio' => 'Bio']);
        $this->failAuditInserts();

        $this->as($this->admin())->deleteJson("/api/admin/users/{$member->id}/avatar")->assertStatus(500);
        $this->as($this->admin())->deleteJson("/api/admin/users/{$member->id}/bio")->assertStatus(500);

        $member->refresh();
        $this->assertNotNull($member->avatar);
        $this->assertSame('Bio', $member->bio);
        Storage::disk('public')->assertExists('avatars/maya.png');
        $this->assertSame([], $this->noticesOf($member));
    }

    public function test_resending_the_verification_email_sends_the_link_and_is_recorded(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $member = User::factory()->unverified()->create(['email' => 'maya@example.com', 'username' => 'maya']);

        $this->as($admin)->postJson("/api/admin/users/{$member->id}/resend-verification")->assertOk();

        Mail::assertSent(VerifyEmailMail::class, 1);
        Mail::assertSent(VerifyEmailMail::class, fn (VerifyEmailMail $mail): bool => $mail->hasTo('maya@example.com'));
        $entry = AdminAuditLog::query()->sole();
        $this->assertSame(AdminAction::UserVerificationResent, $entry->action);
        $this->assertSame('maya', $entry->subject_label);
    }

    public function test_a_verified_member_is_refused_with_a_409_and_nothing_is_recorded(): void
    {
        Mail::fake();
        $member = User::factory()->create();

        $this->as($this->admin())->postJson("/api/admin/users/{$member->id}/resend-verification")
            ->assertStatus(409)
            ->assertJsonPath('message', 'This member has already verified their email.');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_resending_is_limited_to_three_a_minute_per_member(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $member = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();

        foreach (range(1, 3) as $ignored) {
            $this->as($admin)->postJson("/api/admin/users/{$member->id}/resend-verification")->assertOk();
        }

        $this->as($admin)->postJson("/api/admin/users/{$member->id}/resend-verification")
            ->assertStatus(429)
            ->assertHeader('Retry-After');
        $this->as($admin)->postJson("/api/admin/users/{$other->id}/resend-verification")->assertOk();
        Mail::assertSent(VerifyEmailMail::class, 4);
        $this->assertDatabaseCount('admin_audit_logs', 4);
    }

    public function test_a_zero_padded_id_counts_as_the_same_member(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $member = User::factory()->unverified()->create();
        $padded = '000'.$member->id;

        $this->as($admin)->postJson("/api/admin/users/{$member->id}/resend-verification")->assertOk();
        $this->as($admin)->postJson("/api/admin/users/{$padded}/resend-verification")->assertOk();
        $this->as($admin)->postJson("/api/admin/users/{$member->id}/resend-verification")->assertOk();
        $this->as($admin)->postJson("/api/admin/users/{$padded}/resend-verification")->assertStatus(429);

        Mail::assertSent(VerifyEmailMail::class, 3);
    }

    public function test_refused_attempts_never_use_up_a_members_resends(): void
    {
        Mail::fake();
        $member = User::factory()->unverified()->create();
        $staff = $this->moderator();
        $verified = User::factory()->create();

        foreach (range(1, 5) as $ignored) {
            $this->as(User::factory()->create())->postJson("/api/admin/users/{$member->id}/resend-verification")->assertForbidden();
            $this->as($staff)->postJson("/api/admin/users/{$verified->id}/resend-verification")->assertStatus(409);
        }

        foreach (range(1, 3) as $ignored) {
            $this->as($staff)->postJson("/api/admin/users/{$member->id}/resend-verification")->assertOk();
        }

        $this->as($staff)->postJson("/api/admin/users/{$member->id}/resend-verification")->assertStatus(429);
        $this->assertDatabaseCount('admin_audit_logs', 3);
    }

    public function test_a_failing_audit_insert_sends_no_verification_email(): void
    {
        Mail::fake();
        $member = User::factory()->unverified()->create();
        $this->failAuditInserts();

        $this->as($this->admin())->postJson("/api/admin/users/{$member->id}/resend-verification")->assertStatus(500);

        Mail::assertNothingSent();
    }

    /** @return list<array{type: string, data: array<string, mixed>}> */
    private function noticesOf(User $member): array
    {
        return Notification::where('user_id', $member->id)->orderBy('id')->get(['type', 'data'])
            ->map(fn (Notification $notification): array => ['type' => $notification->type, 'data' => $notification->data])
            ->all();
    }
}
