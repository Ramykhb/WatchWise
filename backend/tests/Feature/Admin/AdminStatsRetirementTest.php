<?php

namespace Tests\Feature\Admin;

use App\Models\DiscussionRoom;
use App\Models\Message;
use App\Models\User;
use App\Report\Support\ReportTargetType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/** Each staff action that changes a dashboard figure retires the cached numbers, so the next request shows the change. */
class AdminStatsRetirementTest extends AdminStatsTestCase
{
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Event::fake(self::NOTIFICATION_EVENTS);
        $this->staff = $this->admin();
    }

    /** Reads the dashboard, which caches it, and returns the numbers. */
    private function stats(): array
    {
        return $this->as($this->staff)->getJson('/api/admin/stats')->assertOk()->json();
    }

    public function test_deciding_a_report_retires_the_cached_report_and_room_numbers(): void
    {
        $room = DiscussionRoom::factory()->create();
        $report = $this->reportOn(ReportTargetType::Room, $room);
        $this->assertSame(1, $this->stats()['rooms']['reported']);

        $this->patchJson("/api/admin/reports/{$report->id}", ['status' => 'resolved'])->assertOk();

        $after = $this->stats();
        $this->assertSame(0, $after['reports']['open']);
        $this->assertSame(0, $after['reports']['by_target_type']['room']);
        $this->assertSame(1, $after['reports']['resolved_last_7_days']);
        $this->assertSame(0, $after['rooms']['reported']);
    }

    public function test_locking_and_deleting_a_room_and_deleting_a_message_retire_the_cached_numbers(): void
    {
        $room = DiscussionRoom::factory()->create();
        $message = Message::factory()->create(['room_id' => $room->id]);
        $this->reportOn(ReportTargetType::Message, $message);
        $this->assertSame(['total' => 1, 'public' => 1, 'private' => 0, 'locked' => 0, 'reported' => 1], $this->stats()['rooms']);

        $this->patchJson("/api/admin/rooms/{$room->id}", ['is_locked' => true])->assertOk();
        $this->assertSame(1, $this->stats()['rooms']['locked']);

        $this->deleteJson("/api/admin/rooms/{$room->id}/messages/{$message->id}")->assertOk();
        $after = $this->stats();
        $this->assertSame(0, $after['rooms']['reported']);
        $this->assertSame(0, $after['reports']['open']);

        $this->deleteJson("/api/admin/rooms/{$room->id}")->assertOk();
        $this->assertSame(0, $this->stats()['rooms']['total']);
    }

    public function test_a_failed_room_action_leaves_the_cached_version_alone(): void
    {
        $room = DiscussionRoom::factory()->create();
        $this->stats();
        $version = Cache::get('admin:stats:version');
        $this->failAuditInserts();

        $this->patchJson("/api/admin/rooms/{$room->id}", ['is_locked' => true])->assertStatus(500);

        $this->assertSame($version, Cache::get('admin:stats:version'));
        $this->assertFalse($room->refresh()->is_locked);
    }

    public function test_making_a_list_private_removing_a_title_and_deleting_it_retire_the_cached_numbers(): void
    {
        $first = $this->listCreatedAt(now());
        $second = $this->listCreatedAt(now());
        $second->items()->create(['tmdb_id' => 550, 'media_type' => 'movie', 'position' => 0]);
        $this->reportOn(ReportTargetType::MovieList, $first);
        $this->reportOn(ReportTargetType::MovieList, $second);
        $this->assertSame(['total' => 2, 'public' => 2, 'private' => 0, 'reported' => 2], $this->stats()['lists']);

        $this->patchJson("/api/admin/lists/{$first->id}", ['visibility' => 'private'])->assertOk();
        $this->assertSame(['total' => 2, 'public' => 1, 'private' => 1, 'reported' => 1], $this->stats()['lists']);

        $this->deleteJson("/api/admin/lists/{$second->id}/items/550")->assertOk();
        $this->assertSame(0, $this->stats()['lists']['reported']);
        $this->assertSame(0, $this->stats()['reports']['open']);

        $this->deleteJson("/api/admin/lists/{$second->id}")->assertOk();
        $this->assertSame(1, $this->stats()['lists']['total']);
    }

    public function test_suspending_and_reinstating_a_member_retire_the_cached_account_counts(): void
    {
        $member = User::factory()->create();
        $this->assertSame(0, $this->stats()['users_suspended']);

        $this->patchJson("/api/admin/users/{$member->id}", ['is_active' => false])->assertOk();
        $this->assertSame(1, $this->stats()['users_suspended']);

        $this->patchJson("/api/admin/users/{$member->id}", ['is_active' => true])->assertOk();
        $this->assertSame(0, $this->stats()['users_suspended']);
    }

    public function test_a_change_that_keeps_the_account_state_leaves_the_cache_alone(): void
    {
        $member = User::factory()->create();
        $this->stats();
        $version = Cache::get('admin:stats:version');

        $this->patchJson("/api/admin/users/{$member->id}", ['is_active' => true])->assertOk();

        $this->assertSame($version, Cache::get('admin:stats:version'));
    }

    public function test_a_bulk_suspension_retires_the_cached_account_counts(): void
    {
        $members = User::factory()->count(3)->create();
        $this->assertSame(0, $this->stats()['users_suspended']);

        $this->postJson('/api/admin/users/bulk-suspend', ['user_ids' => $members->pluck('id')->all()])->assertOk();

        $this->assertSame(3, $this->stats()['users_suspended']);
    }

    public function test_the_lift_command_retires_the_cached_account_counts_only_when_it_lifts_someone(): void
    {
        User::factory()->create(['is_active' => false, 'suspended_until' => now()->subMinute()]);
        $this->assertSame(1, $this->stats()['users_suspended']);
        $version = Cache::get('admin:stats:version');

        $this->artisan('admin:lift-suspensions')->assertSuccessful();

        $this->assertNotSame($version, Cache::get('admin:stats:version'));
        $this->assertSame(0, $this->stats()['users_suspended']);

        $version = Cache::get('admin:stats:version');
        $this->artisan('admin:lift-suspensions')->assertSuccessful();

        $this->assertSame($version, Cache::get('admin:stats:version'));
    }

    public function test_deleting_a_star_rating_retires_the_cached_rating_numbers(): void
    {
        $rating = $this->rating(4);
        $this->assertSame(1, $this->stats()['ratings']['total']);

        $this->deleteJson("/api/admin/ratings/{$rating->id}")->assertSuccessful();

        $after = $this->stats();
        $this->assertSame(0, $after['ratings']['total']);
        $this->assertNull($after['ratings']['average']);
    }
}
