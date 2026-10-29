<?php

namespace Tests\Feature\Admin;

use App\Models\User;

class ModeratorPermissionsTest extends AdminTestCase
{
    private function moderator(): User
    {
        return User::factory()->create(['role' => 'moderator']);
    }

    public function test_moderators_can_read_stats_and_the_user_list(): void
    {
        $moderator = $this->moderator();

        $this->as($moderator)->getJson('/api/admin/stats')->assertOk()->assertJsonStructure(['users_total']);
        $this->as($moderator)->getJson('/api/admin/users')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_a_moderator_can_suspend_and_reinstate_a_member(): void
    {
        $member = User::factory()->create(['email' => 'maya@example.com']);
        $moderator = $this->moderator();

        $this->as($moderator)->patchJson("/api/admin/users/{$member->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('user.is_active', false);
        $this->postJson('/api/auth/login', ['email' => 'maya@example.com', 'password' => 'password'])->assertUnauthorized();

        $this->as($moderator)->patchJson("/api/admin/users/{$member->id}", ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('user.is_active', true);
        $this->postJson('/api/auth/login', ['email' => 'maya@example.com', 'password' => 'password'])->assertOk();
    }

    public function test_a_moderator_cannot_change_roles(): void
    {
        $member = User::factory()->create();

        $this->as($this->moderator())->patchJson("/api/admin/users/{$member->id}", ['role' => 'moderator'])->assertForbidden();
        $this->as($this->moderator())->patchJson("/api/admin/users/{$member->id}", ['role' => 'user', 'is_active' => false])->assertForbidden();

        $member->refresh();
        $this->assertSame('user', $member->role);
        $this->assertTrue($member->is_active);
    }

    public function test_a_moderator_cannot_suspend_staff_or_themselves(): void
    {
        $moderator = $this->moderator();

        foreach ([$this->admin(), $this->moderator(), $moderator] as $target) {
            $this->as($moderator)->patchJson("/api/admin/users/{$target->id}", ['is_active' => false])->assertForbidden();
            $this->assertTrue($target->fresh()->is_active);
        }
    }

    public function test_a_moderator_cannot_delete_users_or_reach_movies(): void
    {
        $moderator = $this->moderator();
        $member = User::factory()->create();

        $this->as($moderator)->deleteJson("/api/admin/users/{$member->id}")->assertForbidden();
        $this->as($moderator)->getJson('/api/admin/movies')->assertForbidden();
        $this->as($moderator)->patchJson('/api/admin/movies/550', ['is_hidden' => true])->assertForbidden();

        $this->assertModelExists($member);
    }

    public function test_members_still_cannot_read_stats_or_users(): void
    {
        $member = User::factory()->create();

        $this->as($member)->getJson('/api/admin/stats')->assertForbidden();
        $this->as($member)->getJson('/api/admin/users')->assertForbidden();
        $this->as($member)->patchJson("/api/admin/users/{$member->id}", ['is_active' => false])->assertForbidden();
    }

    public function test_admins_keep_full_control_over_staff(): void
    {
        $moderator = $this->moderator();

        $this->as($this->admin())->patchJson("/api/admin/users/{$moderator->id}", ['is_active' => false])->assertOk();
        $this->as($this->admin())->patchJson("/api/admin/users/{$moderator->id}", ['role' => 'user'])->assertOk();
    }
}
