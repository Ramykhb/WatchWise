<?php

namespace Tests\Feature\Admin;

use App\Models\RefreshToken;
use App\Models\User;

class AdminUsersTest extends AdminTestCase
{
    public function test_users_are_listed_with_filters(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Maya Hart', 'email' => 'maya@example.com']);
        User::factory()->create(['name' => 'Lina Cole', 'is_active' => false]);

        $this->as($admin)->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonMissingPath('data.0.password');

        $this->as($admin)->getJson('/api/admin/users?q=maya')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Maya Hart');
        $this->as($admin)->getJson('/api/admin/users?role=admin')->assertJsonPath('meta.total', 1);
        $this->as($admin)->getJson('/api/admin/users?status=inactive')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Lina Cole');
        $this->as($admin)->getJson('/api/admin/users?per_page=1&page=2')->assertJsonPath('meta.last_page', 3)->assertJsonCount(1, 'data');
    }

    public function test_the_filters_are_validated(): void
    {
        $this->as($this->admin())->getJson('/api/admin/users?role=owner&status=gone')->assertUnprocessable()->assertJsonValidationErrors(['role', 'status']);
    }

    public function test_an_admin_can_promote_a_member(): void
    {
        $member = User::factory()->create();

        $this->as($this->admin())->patchJson("/api/admin/users/{$member->id}", ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('user.role', 'admin');

        $this->assertSame('admin', $member->fresh()->role);
    }

    public function test_an_update_needs_a_role_or_an_active_flag(): void
    {
        $member = User::factory()->create();

        $this->as($this->admin())->patchJson("/api/admin/users/{$member->id}", [])->assertUnprocessable();
        $this->as($this->admin())->patchJson("/api/admin/users/{$member->id}", ['role' => 'owner'])->assertUnprocessable();
    }

    public function test_an_admin_cannot_demote_or_deactivate_themselves(): void
    {
        $admin = $this->admin();

        $this->as($admin)->patchJson("/api/admin/users/{$admin->id}", ['role' => 'user'])->assertForbidden();
        $this->as($admin)->patchJson("/api/admin/users/{$admin->id}", ['is_active' => false])->assertForbidden();

        $admin->refresh();
        $this->assertSame('admin', $admin->role);
        $this->assertTrue($admin->is_active);
    }

    public function test_an_admin_can_keep_their_own_role_while_changing_nothing_harmful(): void
    {
        $admin = $this->admin();

        $this->as($admin)->patchJson("/api/admin/users/{$admin->id}", ['role' => 'admin', 'is_active' => true])->assertOk();
    }

    public function test_deactivating_a_user_revokes_their_sessions_and_blocks_login_and_refresh(): void
    {
        $member = User::factory()->create(['email' => 'maya@example.com']);

        $login = $this->postJson('/api/auth/login', ['email' => 'maya@example.com', 'password' => 'password'])->assertOk();
        $refresh = $login->getCookie('refresh_token', false)->getValue();
        $this->assertSame(1, RefreshToken::where('user_id', $member->id)->whereNull('revoked_at')->count());

        $this->as($this->admin())->patchJson("/api/admin/users/{$member->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('user.is_active', false);

        $this->assertSame(0, RefreshToken::where('user_id', $member->id)->whereNull('revoked_at')->count());
        $this->withCredentials()->withUnencryptedCookie('refresh_token', $refresh)->postJson('/api/auth/refresh')->assertUnauthorized();
        $this->postJson('/api/auth/login', ['email' => 'maya@example.com', 'password' => 'password'])->assertUnauthorized();
    }

    public function test_a_deactivated_user_cannot_use_an_existing_access_token(): void
    {
        $member = User::factory()->create();
        $this->as($member)->getJson('/api/auth/me')->assertOk();

        $member->forceFill(['is_active' => false])->save();

        $this->as($member)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_an_admin_can_delete_a_user_and_their_content(): void
    {
        $member = User::factory()->create();
        $this->review(['user_id' => $member->id]);

        $this->as($this->admin())->deleteJson("/api/admin/users/{$member->id}")->assertOk();

        $this->assertModelMissing($member);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_an_admin_cannot_delete_themselves(): void
    {
        $admin = $this->admin();

        $this->as($admin)->deleteJson("/api/admin/users/{$admin->id}")->assertForbidden();

        $this->assertModelExists($admin);
    }

    public function test_an_unknown_user_is_not_found(): void
    {
        $this->as($this->admin())->deleteJson('/api/admin/users/9999')->assertNotFound();
    }
}
