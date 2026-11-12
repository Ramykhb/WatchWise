<?php

namespace Tests\Feature\Admin;

use App\Admin\Mail\AccountSuspendedMail;
use App\Admin\Support\AdminAction;
use App\Auth\InterfaceServices\AuthServiceInterface;
use App\Models\AdminAuditLog;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/** `POST /api/admin/users/bulk-suspend`: the single suspension, applied to each member on its own. */
class AdminBulkSuspendTest extends AdminTestCase
{
    private function moderator(): User
    {
        return User::factory()->create(['role' => 'moderator']);
    }

    public function test_it_reports_each_outcome_in_the_order_sent_and_changes_only_the_members_it_may(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $fresh = User::factory()->create(['email' => 'fresh@example.com']);
        $other = User::factory()->create();
        $already = User::factory()->create(['is_active' => false]);
        $colleague = $this->moderator();

        $response = $this->as($admin)->postJson('/api/admin/users/bulk-suspend', [
            'user_ids' => [$fresh->id, $already->id, 999999, $admin->id, $colleague->id, $other->id],
            'reason' => 'Spam wave',
        ])->assertOk();

        $response->assertExactJson([
            'results' => [
                ['id' => $fresh->id, 'status' => 'suspended', 'reason' => null],
                ['id' => $already->id, 'status' => 'skipped', 'reason' => 'already_suspended'],
                ['id' => 999999, 'status' => 'skipped', 'reason' => 'not_found'],
                ['id' => $admin->id, 'status' => 'skipped', 'reason' => 'self'],
                ['id' => $colleague->id, 'status' => 'suspended', 'reason' => null],
                ['id' => $other->id, 'status' => 'suspended', 'reason' => null],
            ],
            'suspended' => 3,
        ]);
        $this->assertFalse($fresh->fresh()->is_active);
        $this->assertSame('Spam wave', $fresh->fresh()->suspension_reason);
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertNull($already->fresh()->suspension_reason);
    }

    public function test_each_suspended_member_gets_one_email_one_audit_entry_and_loses_their_sessions(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $members = User::factory()->count(2)->create();
        $members->each(fn (User $member) => RefreshToken::factory()->create(['user_id' => $member->id]));
        $until = now()->addDays(3)->startOfSecond();

        $this->as($admin)->postJson('/api/admin/users/bulk-suspend', [
            'user_ids' => $members->pluck('id')->all(),
            'reason' => 'Spam',
            'suspended_until' => $until->toIso8601String(),
        ])->assertOk()->assertJsonPath('suspended', 2);

        Mail::assertSent(AccountSuspendedMail::class, 2);
        foreach ($members as $member) {
            Mail::assertSent(AccountSuspendedMail::class, fn (AccountSuspendedMail $mail): bool => $mail->hasTo($member->email) && $mail->reason === 'Spam');
            $this->assertSame(0, RefreshToken::query()->where('user_id', $member->id)->whereNull('revoked_at')->count());
            $this->assertTrue($until->equalTo($member->fresh()->suspended_until));
        }
        $entries = AdminAuditLog::query()->orderBy('id')->get();
        $this->assertSame([AdminAction::UserSuspended, AdminAction::UserSuspended], $entries->map->action->all());
        $this->assertSame($members->pluck('id')->all(), $entries->pluck('subject_id')->all());
        $this->assertSame($admin->id, $entries->first()->actor_id);
    }

    public function test_a_moderator_may_only_suspend_plain_members(): void
    {
        Mail::fake();
        $moderator = $this->moderator();
        $admin = $this->admin();
        $colleague = $this->moderator();
        $member = User::factory()->create();

        $this->as($moderator)->postJson('/api/admin/users/bulk-suspend', ['user_ids' => [$admin->id, $colleague->id, $moderator->id, $member->id]])
            ->assertOk()
            ->assertJsonPath('results.0.reason', 'not_allowed')
            ->assertJsonPath('results.1.reason', 'not_allowed')
            ->assertJsonPath('results.2.reason', 'self')
            ->assertJsonPath('results.3.status', 'suspended')
            ->assertJsonPath('suspended', 1);

        $this->assertTrue($admin->fresh()->is_active);
        $this->assertTrue($colleague->fresh()->is_active);
        Mail::assertSent(AccountSuspendedMail::class, 1);
        $this->assertDatabaseCount('admin_audit_logs', 1);
    }

    public function test_one_failing_member_never_undoes_the_others(): void
    {
        Mail::fake();
        $members = User::factory()->count(3)->create();
        $this->failAuditInsertNumber(2);

        $this->as($this->admin())->postJson('/api/admin/users/bulk-suspend', ['user_ids' => $members->pluck('id')->all()])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'suspended')
            ->assertJsonPath('results.1', ['id' => $members[1]->id, 'status' => 'skipped', 'reason' => 'failed'])
            ->assertJsonPath('results.2.status', 'suspended')
            ->assertJsonPath('suspended', 2);

        $this->assertFalse($members[0]->fresh()->is_active);
        $this->assertTrue($members[1]->fresh()->is_active);
        $this->assertFalse($members[2]->fresh()->is_active);
        Mail::assertSent(AccountSuspendedMail::class, 2);
        $this->assertDatabaseCount('admin_audit_logs', 2);
    }

    public function test_a_failure_to_end_the_sessions_after_the_suspension_was_saved_still_counts_as_suspended(): void
    {
        Mail::fake();
        $member = User::factory()->create();
        $this->mock(AuthServiceInterface::class, function ($auth): void {
            $auth->shouldReceive('revokeSessions')->once()->andThrow(new RuntimeException('The token store is unavailable.'));
        });

        $this->as($this->admin())->postJson('/api/admin/users/bulk-suspend', ['user_ids' => [$member->id]])
            ->assertOk()
            ->assertJsonPath('results.0', ['id' => $member->id, 'status' => 'suspended', 'reason' => null])
            ->assertJsonPath('suspended', 1);

        $this->assertFalse($member->fresh()->is_active);
        Mail::assertSent(AccountSuspendedMail::class, 1);
        $this->assertDatabaseCount('admin_audit_logs', 1);
    }

    public function test_a_failing_audit_insert_suspends_nobody(): void
    {
        Mail::fake();
        $members = User::factory()->count(2)->create();
        $this->failAuditInserts();

        $this->as($this->admin())->postJson('/api/admin/users/bulk-suspend', ['user_ids' => $members->pluck('id')->all()])
            ->assertOk()
            ->assertJsonPath('suspended', 0);

        $this->assertSame(2, User::query()->whereIn('id', $members->pluck('id'))->where('is_active', true)->count());
        Mail::assertNothingSent();
    }

    public function test_the_body_is_validated(): void
    {
        $admin = $this->admin();
        $url = '/api/admin/users/bulk-suspend';

        $this->as($admin)->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('user_ids');
        $this->as($admin)->postJson($url, ['user_ids' => []])->assertUnprocessable()->assertJsonValidationErrors('user_ids');
        $this->as($admin)->postJson($url, ['user_ids' => range(1, 51)])->assertUnprocessable()->assertJsonValidationErrors('user_ids');
        $this->as($admin)->postJson($url, ['user_ids' => [3, 3]])->assertUnprocessable()->assertJsonValidationErrors('user_ids.1');
        $this->as($admin)->postJson($url, ['user_ids' => ['abc']])->assertUnprocessable()->assertJsonValidationErrors('user_ids.0');
        $this->as($admin)->postJson($url, ['user_ids' => [3], 'reason' => str_repeat('a', 256)])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->as($admin)->postJson($url, ['user_ids' => [3], 'suspended_until' => now()->subDay()->toIso8601String()])->assertUnprocessable()->assertJsonValidationErrors('suspended_until');
        $this->as($admin)->postJson($url, ['user_ids' => [3], 'suspended_until' => now()->addDays(366)->toIso8601String()])->assertUnprocessable()->assertJsonValidationErrors('suspended_until');
        $this->as($admin)->postJson($url, ['user_ids' => range(100, 149)])->assertOk()->assertJsonPath('suspended', 0);
    }

    public function test_guests_and_members_are_refused(): void
    {
        $member = User::factory()->create();

        $this->postJson('/api/admin/users/bulk-suspend', ['user_ids' => [$member->id]])->assertUnauthorized();
        $this->as($member)->postJson('/api/admin/users/bulk-suspend', ['user_ids' => [$member->id]])->assertForbidden();
        $this->assertTrue($member->fresh()->is_active);
    }
}
