<?php

namespace Tests\Feature\Admin;

use App\Admin\Support\AdminAction;
use App\Admin\Support\AdminSubjectType;
use App\Models\AdminAuditLog;
use App\Models\DiscussionRoom;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Report;
use App\Models\User;
use App\Notification\InterfaceServices\NotificationServiceInterface;
use App\Report\Support\ReportStatus;
use App\Report\Support\ReportTargetType;
use App\Room\Events\RoomMessageDeleted;
use App\Room\Events\RoomMessageSent;
use App\Room\Events\RoomMessagesRead;
use App\Room\Events\RoomsChanged;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Mockery\MockInterface;
use RuntimeException;

/** Staff remove messages, lock rooms and delete them; each is audited, tells the right people and never touches a no-op. */
class AdminRoomActionsTest extends AdminTestCase
{
    private const TEXT = 'A message staff must never quote.';

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([RoomMessageSent::class, RoomMessagesRead::class, RoomMessageDeleted::class, RoomsChanged::class, ...self::NOTIFICATION_EVENTS]);
    }

    private function moderator(): User
    {
        return User::factory()->create(['role' => 'moderator']);
    }

    /**
     * @param  list<User>  $members
     */
    private function room(User $host, array $members = [], array $attributes = []): DiscussionRoom
    {
        $room = DiscussionRoom::factory()->create(['created_by' => $host->id, 'name' => 'Thriller Thursday', 'tmdb_id' => 550, ...$attributes]);

        foreach ([$host, ...$members] as $member) {
            DB::table('room_members')->insert(['room_id' => $room->id, 'user_id' => $member->id]);
        }

        return $room;
    }

    /**
     * @return list<string>
     */
    private function channels(RoomsChanged $event): array
    {
        return array_map(fn (PrivateChannel $channel): string => $channel->name, $event->broadcastOn());
    }

    private function onlyEntry(): AdminAuditLog
    {
        $this->assertDatabaseCount('admin_audit_logs', 1);

        return AdminAuditLog::query()->firstOrFail();
    }

    public function test_deleting_a_message_removes_it_and_its_receipts_and_pushes_the_removal(): void
    {
        [$lina, $omar, $sam] = User::factory()->count(3)->create()->all();
        $room = $this->room($lina, [$omar, $sam]);
        $this->as($omar)->postJson("/api/rooms/{$room->id}/messages", ['content' => self::TEXT])->assertCreated();
        $this->as($omar)->postJson("/api/rooms/{$room->id}/messages", ['content' => 'Stays'])->assertCreated();
        $message = Message::where('content', self::TEXT)->firstOrFail();
        $this->as($lina)->getJson('/api/rooms')->assertJsonPath('rooms.0.unread', 2);

        $this->as($this->moderator())->deleteJson("/api/admin/rooms/{$room->id}/messages/{$message->id}")->assertOk()->assertJsonPath('message', 'Message deleted.');

        $this->assertModelMissing($message);
        $this->assertSame(1, Message::count());
        $this->assertSame(1, Notification::where('type', Notification::ROOM_MESSAGE)->where('user_id', $lina->id)->count());
        $this->as($lina)->getJson('/api/rooms')->assertJsonPath('rooms.0.unread', 1);
        Event::assertDispatched(RoomMessageDeleted::class, fn (RoomMessageDeleted $event): bool => $event->roomId === $room->id
            && $event->messageId === $message->id
            && $event->broadcastOn()[0]->name === "private-room.{$room->id}"
            && $event->broadcastWith() === ['room_id' => $room->id, 'message_id' => $message->id]);
        Event::assertDispatched(RoomsChanged::class, fn (RoomsChanged $event): bool => $event->roomId === $room->id && $this->channels($event) === ['private-rooms.public']);
    }

    public function test_a_message_of_another_room_is_not_found(): void
    {
        $host = User::factory()->create();
        $room = $this->room($host);
        $other = $this->room($host);
        $message = Message::factory()->for($other, 'room')->create();

        $this->as($this->admin())->deleteJson("/api/admin/rooms/{$room->id}/messages/{$message->id}")->assertNotFound();
        $this->as($this->admin())->deleteJson("/api/admin/rooms/{$room->id}/messages/999999")->assertNotFound();

        $this->assertModelExists($message);
        $this->assertDatabaseCount('admin_audit_logs', 0);
        Event::assertNotDispatched(RoomMessageDeleted::class);
    }

    public function test_deleting_a_message_resolves_its_reports_audits_it_and_tells_only_its_author(): void
    {
        $staff = User::factory()->create(['role' => 'moderator', 'name' => 'Alexandra Staffer', 'username' => 'alexstaff']);
        [$lina, $omar] = User::factory()->count(2)->create()->all();
        $room = $this->room($lina, [$omar]);
        $message = Message::factory()->for($room, 'room')->create(['user_id' => $omar->id, 'content' => self::TEXT]);
        $other = Message::factory()->for($room, 'room')->create();
        $reports = Report::factory()->forTarget(ReportTargetType::Message, $message)->count(2)->create();
        $unrelated = Report::factory()->forTarget(ReportTargetType::Message, $other)->create();

        $this->as($staff)->deleteJson("/api/admin/rooms/{$room->id}/messages/{$message->id}")->assertOk();

        foreach ($reports as $report) {
            $report->refresh();
            $this->assertSame(ReportStatus::Resolved, $report->status);
            $this->assertSame($staff->id, $report->resolved_by);
        }
        $this->assertSame(ReportStatus::Open, $unrelated->fresh()->status);

        $entry = $this->onlyEntry();
        $this->assertSame(AdminAction::MessageDeleted, $entry->action);
        $this->assertSame(AdminSubjectType::Message, $entry->subject_type);
        $this->assertSame($message->id, $entry->subject_id);
        $this->assertSame("Message #{$message->id} in room {$room->id}", $entry->subject_label);
        $this->assertSame(['author_id' => $omar->id, 'room_id' => $room->id], $entry->changes);
        $this->assertSame($staff->id, $entry->actor_id);
        $this->assertStringNotContainsString(self::TEXT, json_encode($entry->getAttributes()));

        $this->assertSame(1, Notification::count());
        $notice = Notification::firstOrFail();
        $this->assertSame($omar->id, $notice->user_id);
        $this->assertSame(Notification::MESSAGE_REMOVED, $notice->type);
        $this->assertSame(['room_id' => $room->id, 'room_name' => 'Thriller Thursday', 'tmdb_id' => 550, 'media_type' => 'movie', 'action' => 'removed'], $notice->data);
        $feed = $this->as($omar)->getJson('/api/notifications')->assertOk()
            ->assertJsonPath('notifications.0.kind', 'message_removed')
            ->assertJsonPath('notifications.0.actor', ['id' => 0, 'username' => 'watchwise', 'name' => 'WatchWise', 'avatar' => null])
            ->assertJsonPath('notifications.0.text', 'removed your message in')
            ->assertJsonPath('notifications.0.target', 'Thriller Thursday')
            ->assertJsonPath('notifications.0.film', ['tmdb_id' => 550, 'media_type' => 'movie'])
            ->assertJsonPath('notifications.0.to', "/rooms/{$room->id}");
        $this->assertStringNotContainsString('alexstaff', $feed->getContent());
        $this->assertStringNotContainsString(self::TEXT, $feed->getContent());
        $this->as($omar)->getJson('/api/me')->assertJsonPath('counts.unread_notifications', 1);
    }

    public function test_a_staff_member_removing_their_own_message_tells_nobody(): void
    {
        $admin = $this->admin();
        $room = $this->room($admin);
        $message = Message::factory()->for($room, 'room')->create(['user_id' => $admin->id]);

        $this->as($admin)->deleteJson("/api/admin/rooms/{$room->id}/messages/{$message->id}")->assertOk();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_a_failing_audit_insert_rolls_a_message_deletion_back_and_sends_nothing(): void
    {
        $host = User::factory()->create();
        $room = $this->room($host);
        $message = Message::factory()->for($room, 'room')->create();
        $report = Report::factory()->forTarget(ReportTargetType::Message, $message)->create();
        $this->failAuditInserts();

        $this->as($this->admin())->deleteJson("/api/admin/rooms/{$room->id}/messages/{$message->id}")->assertStatus(500);

        $this->assertModelExists($message);
        $this->assertSame(ReportStatus::Open, $report->fresh()->status);
        $this->assertDatabaseCount('notifications', 0);
        Event::assertNotDispatched(RoomMessageDeleted::class);
        Event::assertNotDispatched(RoomsChanged::class);
    }

    public function test_a_failing_notice_does_not_undo_the_removal(): void
    {
        Exceptions::fake();
        $room = $this->room(User::factory()->create());
        $message = Message::factory()->for($room, 'room')->create();
        $this->mock(NotificationServiceInterface::class, function (MockInterface $notifications): void {
            $notifications->shouldReceive('roomMessageDeleted')->once();
            $notifications->shouldReceive('messageRemoved')->once()->andThrow(new RuntimeException('The notification store is unavailable.'));
        });

        $this->as($this->admin())->deleteJson("/api/admin/rooms/{$room->id}/messages/{$message->id}")->assertOk();

        $this->assertModelMissing($message);
        $this->assertDatabaseCount('admin_audit_logs', 1);
        Exceptions::assertReportedCount(1);
    }

    public function test_locking_stops_every_post_but_not_reading_joining_or_inviting(): void
    {
        [$lina, $omar, $newcomer] = User::factory()->count(3)->create()->all();
        $public = $this->room($lina, [$omar]);
        $private = $this->room($lina, [$omar], ['is_private' => true]);

        foreach ([$public, $private] as $room) {
            $this->as($this->moderator())->patchJson("/api/admin/rooms/{$room->id}", ['is_locked' => true])->assertOk()->assertJsonPath('room.is_locked', true);
        }

        foreach ([$lina, $omar] as $member) {
            $this->as($member)->postJson("/api/rooms/{$public->id}/messages", ['content' => 'Hello?'])
                ->assertForbidden()
                ->assertJsonPath('code', 'room_locked');
        }
        $this->assertDatabaseCount('messages', 0);
        $this->as($omar)->getJson("/api/rooms/{$public->id}/messages")->assertOk();
        $this->as($newcomer)->postJson("/api/rooms/{$public->id}/join")->assertOk();
        $this->as($lina)->postJson("/api/rooms/{$private->id}/invites", ['invitees' => [$newcomer->username]])->assertOk();
        $this->as($lina)->getJson('/api/rooms')->assertJsonPath('rooms.0.is_locked', true);

        $this->as($this->admin())->patchJson("/api/admin/rooms/{$public->id}", ['is_locked' => false])->assertOk()->assertJsonPath('room.is_locked', false);
        $this->as($lina)->postJson("/api/rooms/{$public->id}/messages", ['content' => 'Back'])->assertCreated();
    }

    public function test_locking_audits_notifies_the_creator_without_the_staff_identity_and_refreshes_lists(): void
    {
        $staff = User::factory()->create(['role' => 'moderator', 'name' => 'Alexandra Staffer', 'username' => 'alexstaff']);
        [$lina, $omar] = User::factory()->count(2)->create()->all();
        $room = $this->room($lina, [$omar], ['is_private' => true]);

        $this->as($staff)->patchJson("/api/admin/rooms/{$room->id}", ['is_locked' => true])->assertOk();
        $this->as($staff)->patchJson("/api/admin/rooms/{$room->id}", ['is_locked' => false])->assertOk();

        [$locked, $unlocked] = AdminAuditLog::query()->orderBy('id')->get()->all();
        $this->assertSame(AdminAction::RoomLocked, $locked->action);
        $this->assertSame(AdminAction::RoomUnlocked, $unlocked->action);
        $this->assertSame(AdminSubjectType::Room, $locked->subject_type);
        $this->assertSame($room->id, $locked->subject_id);
        $this->assertSame('Room "Thriller Thursday"', $locked->subject_label);
        $this->assertSame(['is_locked' => ['from' => false, 'to' => true]], $locked->changes);
        $this->assertSame(['is_locked' => ['from' => true, 'to' => false]], $unlocked->changes);

        $this->assertSame(
            [[Notification::ROOM_LOCKED, $lina->id, 'locked'], [Notification::ROOM_UNLOCKED, $lina->id, 'unlocked']],
            Notification::orderBy('id')->get()->map(fn (Notification $notice): array => [$notice->type, $notice->user_id, $notice->data['action']])->all(),
        );
        $this->assertSame(['room_id' => $room->id, 'room_name' => 'Thriller Thursday', 'tmdb_id' => 550, 'media_type' => 'movie', 'action' => 'locked'], Notification::orderBy('id')->firstOrFail()->data);
        $feed = $this->as($lina)->getJson('/api/notifications')->assertOk()
            ->assertJsonPath('notifications.1.text', 'locked your room')
            ->assertJsonPath('notifications.1.actor.username', 'watchwise')
            ->assertJsonPath('notifications.0.text', 'unlocked your room');
        $this->assertStringNotContainsString('alexstaff', $feed->getContent());

        Event::assertDispatchedTimes(RoomsChanged::class, 2);
        Event::assertDispatched(RoomsChanged::class, fn (RoomsChanged $event): bool => $this->channels($event) === ["private-rooms.user.{$lina->id}", "private-rooms.user.{$omar->id}"]);
    }

    public function test_setting_the_state_a_room_already_has_writes_and_sends_nothing(): void
    {
        $lina = User::factory()->create();
        $open = $this->room($lina);
        $locked = $this->room($lina, attributes: ['is_locked' => true]);
        $admin = $this->admin();

        $this->as($admin)->patchJson("/api/admin/rooms/{$open->id}", ['is_locked' => false])->assertOk()->assertJsonPath('room.is_locked', false);
        $this->as($admin)->patchJson("/api/admin/rooms/{$locked->id}", ['is_locked' => true])->assertOk()->assertJsonPath('room.is_locked', true);

        $this->assertDatabaseCount('admin_audit_logs', 0);
        $this->assertDatabaseCount('notifications', 0);
        Event::assertNotDispatched(RoomsChanged::class);
        $this->as($admin)->patchJson("/api/admin/rooms/{$open->id}", [])->assertUnprocessable();
        $this->as($admin)->patchJson("/api/admin/rooms/{$open->id}", ['is_locked' => 'maybe'])->assertUnprocessable();
    }

    public function test_a_staff_member_locking_their_own_room_is_not_notified(): void
    {
        $admin = $this->admin();
        $room = $this->room($admin);

        $this->as($admin)->patchJson("/api/admin/rooms/{$room->id}", ['is_locked' => true])->assertOk();

        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('admin_audit_logs', 1);
    }

    public function test_a_failing_audit_insert_leaves_the_room_unlocked_and_sends_nothing(): void
    {
        $room = $this->room(User::factory()->create());
        $this->failAuditInserts();

        $this->as($this->admin())->patchJson("/api/admin/rooms/{$room->id}", ['is_locked' => true])->assertStatus(500);

        $this->assertFalse($room->fresh()->is_locked);
        $this->assertDatabaseCount('notifications', 0);
        Event::assertNotDispatched(RoomsChanged::class);
    }

    public function test_an_admin_deletes_a_room_through_the_rooms_own_path_and_nobody_is_told_twice(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Alexandra Staffer', 'username' => 'alexstaff']);
        [$lina, $omar, $sam] = User::factory()->count(3)->create()->all();
        $room = $this->room($lina, [$omar], ['is_private' => true]);
        DB::table('room_invites')->insert(['room_id' => $room->id, 'user_id' => $sam->id, 'invited_by' => $lina->id]);
        $this->as($omar)->postJson("/api/rooms/{$room->id}/messages", ['content' => self::TEXT])->assertCreated();
        $message = Message::firstOrFail();
        $roomReport = Report::factory()->forTarget(ReportTargetType::Room, $room)->create();
        $messageReport = Report::factory()->forTarget(ReportTargetType::Message, $message)->create();
        $otherRoom = $this->room($lina);
        $unrelated = Report::factory()->forTarget(ReportTargetType::Room, $otherRoom)->create();
        Notification::where('user_id', '!=', $lina->id)->delete();

        $this->as($admin)->deleteJson("/api/admin/rooms/{$room->id}")->assertOk()->assertJsonPath('message', 'Room deleted.');

        $this->assertModelMissing($room);
        $this->assertDatabaseCount('messages', 0);
        $this->assertSame(0, Notification::where('type', Notification::ROOM_MESSAGE)->count());
        foreach ([$roomReport, $messageReport] as $report) {
            $report->refresh();
            $this->assertSame(ReportStatus::Resolved, $report->status);
            $this->assertSame($admin->id, $report->resolved_by);
        }
        $this->assertSame(ReportStatus::Open, $unrelated->fresh()->status);

        $this->assertSame([Notification::ROOM_REMOVED], Notification::where('user_id', $lina->id)->pluck('type')->all());
        $this->assertSame([Notification::ROOM_DELETED], Notification::where('user_id', $omar->id)->pluck('type')->all());
        $this->assertSame([Notification::ROOM_DELETED], Notification::where('user_id', $sam->id)->pluck('type')->all());
        $this->assertSame(0, Notification::where('user_id', $admin->id)->count());
        Event::assertDispatched(RoomsChanged::class, fn (RoomsChanged $event): bool => $event->roomId === $room->id
            && $this->channels($event) === array_map(fn (int $id): string => "private-rooms.user.{$id}", collect([$admin, $lina, $omar, $sam])->pluck('id')->sort()->values()->all()));

        $entry = $this->onlyEntry();
        $this->assertSame(AdminAction::RoomDeleted, $entry->action);
        $this->assertSame(AdminSubjectType::Room, $entry->subject_type);
        $this->assertSame($room->id, $entry->subject_id);
        $this->assertSame('Room "Thriller Thursday"', $entry->subject_label);
        $this->assertSame(['creator_id' => $lina->id, 'reports_resolved' => 2], $entry->changes);
    }

    public function test_the_staff_notices_about_a_room_go_with_it(): void
    {
        $admin = $this->admin();
        $lina = User::factory()->create();
        $room = $this->room($lina);
        $message = Message::factory()->for($room, 'room')->create(['user_id' => $lina->id]);
        $this->as($admin)->patchJson("/api/admin/rooms/{$room->id}", ['is_locked' => true])->assertOk();
        $this->as($admin)->deleteJson("/api/admin/rooms/{$room->id}/messages/{$message->id}")->assertOk();
        $this->assertSame(2, Notification::count());

        $this->as($admin)->deleteJson("/api/admin/rooms/{$room->id}")->assertOk();

        $this->assertSame([Notification::ROOM_REMOVED], Notification::pluck('type')->all());
    }

    public function test_a_failing_audit_insert_keeps_the_room_and_sends_nothing(): void
    {
        $lina = User::factory()->create();
        $room = $this->room($lina);
        $message = Message::factory()->for($room, 'room')->create();
        $report = Report::factory()->forTarget(ReportTargetType::Message, $message)->create();
        $this->failAuditInserts();

        $this->as($this->admin())->deleteJson("/api/admin/rooms/{$room->id}")->assertStatus(500);

        $this->assertModelExists($room);
        $this->assertModelExists($message);
        $this->assertSame(ReportStatus::Open, $report->fresh()->status);
        $this->assertDatabaseCount('notifications', 0);
        Event::assertNotDispatched(RoomsChanged::class);
    }

    public function test_moderators_cannot_delete_a_room(): void
    {
        $room = $this->room(User::factory()->create());

        $this->as($this->moderator())->deleteJson("/api/admin/rooms/{$room->id}")->assertForbidden();

        $this->assertModelExists($room);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }
}
