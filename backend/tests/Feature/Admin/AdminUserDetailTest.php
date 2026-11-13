<?php

namespace Tests\Feature\Admin;

use App\Admin\Support\AdminAction;
use App\Models\AdminAuditLog;
use App\Models\AdminUserNote;
use App\Models\DiscussionRoom;
use App\Models\MovieList;
use App\Models\RefreshToken;
use App\Models\Report;
use App\Models\SocialAccount;
use App\Models\User;
use App\Report\Support\ReportTargetType;
use Illuminate\Support\Facades\DB;

/** `GET /api/admin/users/{user}`: one member with everything staff need to judge a case, and nothing secret. */
class AdminUserDetailTest extends AdminTestCase
{
    private function moderator(): User
    {
        return User::factory()->create(['role' => 'moderator']);
    }

    public function test_the_detail_holds_the_row_fields_and_the_extra_sections(): void
    {
        $member = User::factory()->create(['name' => 'Mia', 'username' => 'mia', 'bio' => 'Hello there', 'profile_visibility' => 'followers']);

        $response = $this->as($this->admin())->getJson("/api/admin/users/{$member->id}")->assertOk();

        $response->assertJsonPath('user.id', $member->id)
            ->assertJsonPath('user.username', 'mia')
            ->assertJsonPath('user.status', 'active')
            ->assertJsonPath('user.role', 'user')
            ->assertJsonPath('user.profile', ['bio' => 'Hello there', 'avatar' => null, 'profile_visibility' => 'followers'])
            ->assertJsonPath('user.suspension', null)
            ->assertJsonPath('user.notes_count', 0)
            ->assertJsonPath('user.social_providers', [])
            ->assertJsonPath('user.active_sessions', 0)
            ->assertJsonPath('user.recent_audit', []);
        $this->assertSame(
            ['reviews_with_text', 'ratings', 'lists', 'followers', 'following', 'rooms_created', 'reports_against_open', 'reports_filed'],
            array_keys($response->json('user.counts')),
        );
    }

    public function test_the_counts_cover_the_members_activity(): void
    {
        $member = User::factory()->create();
        $this->review(['user_id' => $member->id, 'tmdb_id' => 1, 'content' => 'Text', 'rating' => 5]);
        $this->review(['user_id' => $member->id, 'tmdb_id' => 2, 'content' => null, 'sentiment' => null, 'rating' => 3]);
        $this->review(['user_id' => $member->id, 'tmdb_id' => 3, 'content' => 'More text', 'rating' => 4]);
        MovieList::create(['user_id' => $member->id, 'title' => 'One', 'is_public' => false]);
        MovieList::create(['user_id' => $member->id, 'title' => 'Two', 'is_public' => true]);
        DiscussionRoom::create(['tmdb_id' => 550, 'name' => 'Room', 'created_by' => $member->id, 'is_private' => true]);
        User::factory()->count(2)->create()->each(fn (User $fan) => $fan->following()->attach($member->id));
        $member->following()->attach(User::factory()->create()->id);
        Report::factory()->create(['target_type' => ReportTargetType::Profile, 'target_id' => $member->id]);
        Report::factory()->resolved()->create(['target_type' => ReportTargetType::Profile, 'target_id' => $member->id]);
        Report::factory()->create(['reporter_id' => $member->id]);

        $this->as($this->admin())->getJson("/api/admin/users/{$member->id}")
            ->assertOk()
            ->assertJsonPath('user.reviews_count', 2)
            ->assertJsonPath('user.counts', [
                'reviews_with_text' => 2,
                'ratings' => 3,
                'lists' => 2,
                'followers' => 2,
                'following' => 1,
                'rooms_created' => 1,
                'reports_against_open' => 1,
                'reports_filed' => 1,
            ]);
    }

    public function test_it_lists_provider_names_and_open_sessions_but_no_tokens_or_ids(): void
    {
        $member = User::factory()->create();
        SocialAccount::create(['user_id' => $member->id, 'provider' => 'google', 'provider_id' => 'google-secret-id-1']);
        SocialAccount::create(['user_id' => $member->id, 'provider' => 'discord', 'provider_id' => 'discord-secret-id-2']);
        RefreshToken::factory()->count(2)->create(['user_id' => $member->id]);
        RefreshToken::factory()->revoked()->create(['user_id' => $member->id]);
        RefreshToken::factory()->expired()->create(['user_id' => $member->id]);

        $response = $this->as($this->admin())->getJson("/api/admin/users/{$member->id}")->assertOk();

        $response->assertJsonPath('user.social_providers', ['discord', 'google'])->assertJsonPath('user.active_sessions', 2);
        $body = $response->getContent();

        foreach (['secret-id', 'token_hash', 'access_token', 'password', 'pending_email', 'deletion', 'session_version', 'remember_token'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }
    }

    public function test_the_suspension_shows_its_reason_and_end_only_while_suspended(): void
    {
        $until = now()->addDays(3)->startOfSecond();
        $suspended = User::factory()->create(['is_active' => false]);
        $suspended->forceFill(['suspension_reason' => 'Spamming reviews', 'suspended_until' => $until])->save();
        $legacy = User::factory()->create(['is_active' => false]);
        $admin = $this->admin();

        $this->as($admin)->getJson("/api/admin/users/{$suspended->id}")
            ->assertJsonPath('user.suspension.reason', 'Spamming reviews')
            ->assertJsonPath('user.suspension.until', $until->toJSON())
            ->assertJsonPath('user.status', 'inactive');
        $this->as($admin)->getJson("/api/admin/users/{$legacy->id}")->assertJsonPath('user.suspension', ['reason' => null, 'until' => null]);
    }

    public function test_the_note_count_and_the_last_ten_audit_entries_about_the_member_show_up(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create();
        AdminUserNote::factory()->about($member)->count(3)->create();
        AdminAuditLog::factory()->count(12)->create(['subject_id' => $member->id]);
        AdminAuditLog::factory()->create(['subject_id' => $other->id]);
        AdminAuditLog::factory()->action(AdminAction::ReviewDeleted)->create(['subject_id' => $member->id]);

        $response = $this->as($this->admin())->getJson("/api/admin/users/{$member->id}")->assertOk();

        $response->assertJsonPath('user.notes_count', 3);
        $this->assertCount(10, $response->json('user.recent_audit'));
        $this->assertSame([$member->id], array_values(array_unique(array_column(array_column($response->json('user.recent_audit'), 'subject'), 'id'))));
        $this->assertSame('user', $response->json('user.recent_audit.0.subject.type'));
    }

    public function test_a_moderator_sees_the_member_but_not_the_audit_entries(): void
    {
        $member = User::factory()->create();
        AdminAuditLog::factory()->create(['subject_id' => $member->id]);

        $this->as($this->moderator())->getJson("/api/admin/users/{$member->id}")
            ->assertOk()
            ->assertJsonPath('user.id', $member->id)
            ->assertJsonPath('user.recent_audit', null);
    }

    public function test_the_query_count_does_not_grow_with_the_members_data(): void
    {
        $admin = $this->admin();
        $bare = User::factory()->create();
        $busy = User::factory()->create();
        AdminAuditLog::factory()->create(['subject_id' => $bare->id]);

        foreach (['google', 'github', 'discord'] as $provider) {
            SocialAccount::create(['user_id' => $busy->id, 'provider' => $provider, 'provider_id' => $provider.'-id']);
        }

        foreach (range(1, 12) as $n) {
            $this->review(['user_id' => $busy->id, 'tmdb_id' => $n]);
            MovieList::create(['user_id' => $busy->id, 'title' => "List {$n}"]);
            DiscussionRoom::create(['tmdb_id' => $n, 'name' => "Room {$n}", 'created_by' => $busy->id]);
            User::factory()->create()->following()->attach($busy->id);
            RefreshToken::factory()->create(['user_id' => $busy->id]);
            AdminUserNote::factory()->about($busy)->create();
            AdminAuditLog::factory()->create(['subject_id' => $busy->id]);
            Report::factory()->create(['target_type' => ReportTargetType::Profile, 'target_id' => $busy->id]);
        }

        $this->as($admin)->getJson("/api/admin/users/{$bare->id}")->assertOk();
        $queries = fn (User $member): int => $this->countQueries(fn () => $this->as($admin)->getJson("/api/admin/users/{$member->id}")->assertOk());

        $this->assertSame($queries($bare), $queries($busy));
    }

    public function test_guests_members_and_unknown_ids_are_refused(): void
    {
        $member = User::factory()->create();

        $this->getJson("/api/admin/users/{$member->id}")->assertUnauthorized();
        $this->as(User::factory()->create())->getJson("/api/admin/users/{$member->id}")->assertForbidden();
        $this->as($this->admin())->getJson('/api/admin/users/999999')->assertNotFound();
    }

    public function test_staff_may_open_any_account_including_their_own(): void
    {
        $admin = $this->admin();
        $moderator = $this->moderator();

        $this->as($moderator)->getJson("/api/admin/users/{$admin->id}")->assertOk()->assertJsonPath('user.role', 'admin');
        $this->as($admin)->getJson("/api/admin/users/{$admin->id}")->assertOk();
    }

    private function countQueries(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
