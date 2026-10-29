<?php

namespace Tests\Feature\Admin;

use App\Models\User;

class AdminUserInsightsTest extends AdminTestCase
{
    public function test_users_report_status_review_count_and_last_activity(): void
    {
        $author = User::factory()->unverified()->create(['name' => 'Jad']);
        $this->review(['user_id' => $author->id, 'tmdb_id' => 1]);
        $this->review(['user_id' => $author->id, 'tmdb_id' => 2, 'content' => null, 'sentiment' => null]);
        User::factory()->create(['name' => 'Off', 'is_active' => false]);
        User::factory()->create(['name' => 'Seen', 'last_active_at' => now()->subHour()]);

        $rows = collect($this->as($this->admin())->getJson('/api/admin/users')->assertOk()->json('data'))->keyBy('name');

        $this->assertSame('unverified', $rows['Jad']['status']);
        $this->assertSame(1, $rows['Jad']['reviews_count']);
        $this->assertSame('inactive', $rows['Off']['status']);
        $this->assertSame('active', $rows['Seen']['status']);
        $this->assertNotNull($rows['Seen']['last_active_at']);
        $this->assertNull($rows['Jad']['last_active_at']);
    }

    public function test_users_can_be_filtered_by_the_unverified_status(): void
    {
        User::factory()->unverified()->create(['name' => 'Jad']);
        User::factory()->unverified()->create(['name' => 'Off', 'is_active' => false]);
        User::factory()->create(['name' => 'Fine']);
        $admin = $this->admin();

        $this->as($admin)->getJson('/api/admin/users?status=unverified')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Jad');
        $this->as($admin)->getJson('/api/admin/users?status=active')->assertJsonPath('meta.total', 2);
        $this->as($admin)->getJson('/api/admin/users?status=inactive')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Off');
    }

    public function test_the_page_summarises_all_accounts(): void
    {
        User::factory()->unverified()->create();
        User::factory()->create(['is_active' => false]);
        User::factory()->create(['role' => 'moderator']);

        $this->as($this->admin())->getJson('/api/admin/users?status=inactive')
            ->assertJsonPath('summary', ['total' => 4, 'active' => 2, 'unverified' => 1, 'inactive' => 1, 'admins' => 1, 'moderators' => 1]);
    }

    public function test_an_admin_can_make_a_member_a_moderator(): void
    {
        $member = User::factory()->create();

        $this->as($this->admin())->patchJson("/api/admin/users/{$member->id}", ['role' => 'moderator'])
            ->assertOk()
            ->assertJsonPath('user.role', 'moderator')
            ->assertJsonPath('user.reviews_count', 0);

        $this->assertSame('moderator', $member->fresh()->role);
        $this->as($this->admin())->getJson('/api/admin/users?role=moderator')->assertJsonPath('meta.total', 1);
    }

    public function test_a_request_records_when_the_member_was_last_active_at_most_once_in_a_while(): void
    {
        $member = User::factory()->create();

        $this->as($member)->getJson('/api/auth/me')->assertOk();
        $first = $member->fresh()->last_active_at;
        $this->assertNotNull($first);

        User::whereKey($member->id)->update(['last_active_at' => now()->subMinutes(2)]);
        $recent = $member->fresh()->last_active_at;
        $this->as($member)->getJson('/api/auth/me')->assertOk();
        $this->assertEquals($recent, $member->fresh()->last_active_at);

        User::whereKey($member->id)->update(['last_active_at' => now()->subMinutes(10)]);
        $stale = $member->fresh()->last_active_at;
        $this->as($member)->getJson('/api/auth/me')->assertOk();
        $this->assertTrue($member->fresh()->last_active_at->gt($stale));
    }

    public function test_the_last_activity_is_not_leaked_by_the_public_user_payload(): void
    {
        $member = User::factory()->create();

        $this->as($member)->getJson('/api/auth/me')->assertOk()->assertJsonMissingPath('user.last_active_at');
    }
}
