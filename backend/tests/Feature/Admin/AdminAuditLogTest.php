<?php

namespace Tests\Feature\Admin;

use App\Admin\Support\AdminAction;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** `GET /api/admin/audit-log`: admins only, newest first, filterable and paged. */
class AdminAuditLogTest extends AdminTestCase
{
    public function test_guests_are_unauthenticated(): void
    {
        $this->getJson('/api/admin/audit-log')->assertUnauthorized();
    }

    public function test_members_and_moderators_get_forbidden(): void
    {
        AdminAuditLog::factory()->create();

        $this->as(User::factory()->create())->getJson('/api/admin/audit-log')->assertForbidden();
        $this->as(User::factory()->create(['role' => 'moderator']))->getJson('/api/admin/audit-log')->assertForbidden();
    }

    public function test_an_admin_reads_entries_in_the_documented_shape(): void
    {
        $actor = User::factory()->admin()->create(['name' => 'Ada Admin']);
        $entry = AdminAuditLog::factory()->by($actor)->create([
            'subject_id' => 42,
            'subject_label' => 'maya',
            'changes' => ['is_active' => ['from' => true, 'to' => false]],
        ]);

        $this->as($this->admin())->getJson('/api/admin/audit-log')
            ->assertOk()
            ->assertExactJson([
                'data' => [[
                    'id' => $entry->id,
                    'action' => 'user.suspended',
                    'actor' => ['id' => $actor->id, 'name' => 'Ada Admin', 'username' => $actor->username, 'role' => 'admin'],
                    'subject' => ['type' => 'user', 'id' => 42, 'label' => 'maya'],
                    'changes' => ['is_active' => ['from' => true, 'to' => false]],
                    'created_at' => $entry->created_at->toJSON(),
                ]],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 1],
            ]);
    }

    public function test_the_actor_role_is_the_one_recorded_at_the_time(): void
    {
        $actor = User::factory()->create(['role' => 'moderator']);
        AdminAuditLog::factory()->by($actor)->create();
        $actor->forceFill(['role' => 'user'])->save();

        $this->as($this->admin())->getJson('/api/admin/audit-log')->assertJsonPath('data.0.actor.role', 'moderator');
    }

    public function test_entries_come_newest_first_with_ties_broken_by_id(): void
    {
        $oldest = AdminAuditLog::factory()->create(['created_at' => now()->subDays(2)]);
        $newest = AdminAuditLog::factory()->create(['created_at' => now()->subMinute()]);
        $tieFirst = AdminAuditLog::factory()->create(['created_at' => now()->subHour()]);
        $tieSecond = AdminAuditLog::factory()->create(['created_at' => $tieFirst->created_at]);

        $this->as($this->admin())->getJson('/api/admin/audit-log')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$newest->id, $tieSecond->id, $tieFirst->id, $oldest->id]);
    }

    public function test_entries_can_be_filtered(): void
    {
        $admin = $this->admin();
        $other = $this->admin();
        $suspension = AdminAuditLog::factory()->by($admin)->create(['subject_id' => 7]);
        $deletion = AdminAuditLog::factory()->by($other)->action(AdminAction::UserDeleted)->create(['subject_id' => 7]);
        $review = AdminAuditLog::factory()->by($admin)->action(AdminAction::ReviewDeleted)->create(['subject_id' => 7]);
        $movie = AdminAuditLog::factory()->by($admin)->action(AdminAction::MovieUpdated)->create(['subject_id' => 550]);

        $ids = fn (string $query): array => $this->as($admin)->getJson('/api/admin/audit-log?'.$query)->assertOk()->json('data.*.id');

        $this->assertEqualsCanonicalizing([$suspension->id], $ids('action=user.suspended'));
        $this->assertEqualsCanonicalizing([$suspension->id, $review->id, $movie->id], $ids('actor_id='.$admin->id));
        $this->assertEqualsCanonicalizing([$deletion->id], $ids('actor_id='.$other->id));
        $this->assertEqualsCanonicalizing([$review->id], $ids('subject_type=review'));
        $this->assertEqualsCanonicalizing([$suspension->id, $deletion->id], $ids('subject_type=user&subject_id=7'));
        $this->assertEqualsCanonicalizing([$suspension->id], $ids('subject_type=user&subject_id=7&actor_id='.$admin->id.'&action=user.suspended'));
        $this->assertEqualsCanonicalizing([$movie->id], $ids('subject_type=movie&subject_id=550'));
        $this->assertSame([], $ids('subject_type=movie&subject_id=7'));
    }

    public function test_the_filters_are_validated(): void
    {
        $this->as($this->admin())->getJson('/api/admin/audit-log?action=user.exploded&actor_id=abc&subject_type=planet&per_page=51&page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['action', 'actor_id', 'subject_type', 'per_page', 'page']);

        $this->as($this->admin())->getJson('/api/admin/audit-log?subject_id=7')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subject_type']);
    }

    public function test_entries_are_paged(): void
    {
        $entries = collect(range(1, 5))->map(fn (int $minutes) => AdminAuditLog::factory()->create(['created_at' => now()->subMinutes($minutes)]));
        $admin = $this->admin();

        $this->as($admin)->getJson('/api/admin/audit-log?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta', ['current_page' => 1, 'last_page' => 3, 'per_page' => 2, 'total' => 5])
            ->assertJsonPath('data.*.id', [$entries[0]->id, $entries[1]->id]);

        $this->as($admin)->getJson('/api/admin/audit-log?per_page=2&page=3')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 3)
            ->assertJsonPath('data.*.id', [$entries[4]->id]);
    }

    public function test_the_query_count_does_not_grow_with_the_number_of_entries(): void
    {
        $admin = $this->admin();
        AdminAuditLog::factory()->count(2)->create();
        $this->as($admin)->getJson('/api/admin/audit-log')->assertOk();

        $few = $this->queriesDuring(fn () => $this->as($admin)->getJson('/api/admin/audit-log?per_page=50')->assertJsonCount(2, 'data'));

        AdminAuditLog::factory()->count(25)->create();
        $many = $this->queriesDuring(fn () => $this->as($admin)->getJson('/api/admin/audit-log?per_page=50')->assertJsonCount(27, 'data'));

        $this->assertSame($few, $many);
    }

    private function queriesDuring(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }
}
