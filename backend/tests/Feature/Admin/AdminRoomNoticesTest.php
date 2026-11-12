<?php

namespace Tests\Feature\Admin;

use App\Models\DiscussionRoom;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use App\Room\Events\RoomMessageDeleted;
use App\Room\Events\RoomMessageSent;
use App\Room\Events\RoomMessagesRead;
use App\Room\Events\RoomsChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/** What a removed message or room leaves in other members' notifications, and who hears of a staff deletion. */
class AdminRoomNoticesTest extends AdminTestCase
{
    private const SECRET = 'Meet me behind the cinema at nine';

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([RoomMessageSent::class, RoomMessagesRead::class, RoomMessageDeleted::class, RoomsChanged::class, ...self::NOTIFICATION_EVENTS]);
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

    private function say(User $author, DiscussionRoom $room, string $content): Message
    {
        $this->as($author)->postJson("/api/rooms/{$room->id}/messages", ['content' => $content])->assertCreated();

        return Message::where('room_id', $room->id)->where('content', $content)->firstOrFail();
    }

    private function newMessages(User $user): ?Notification
    {
        return Notification::where('user_id', $user->id)->where('type', Notification::NEW_MESSAGES)->first();
    }

    private function mentionsOf(User $user): int
    {
        return Notification::where('user_id', $user->id)->where('type', Notification::MENTION)->count();
    }

    public function test_deleting_a_message_withdraws_the_mentions_that_quote_it(): void
    {
        [$lina, $omar, $sam] = User::factory()->count(3)->create()->all();
        $room = $this->room($lina, [$omar, $sam]);
        $secret = $this->say($omar, $room, "@{$sam->username} ".self::SECRET);
        $this->say($omar, $room, "@{$sam->username} Bring popcorn");
        $this->say($omar, $room, "@{$lina->username} Hello");
        $this->assertSame(2, $this->mentionsOf($sam));
        $this->assertSame(['message_id' => $secret->id], ['message_id' => Notification::where('user_id', $sam->id)->where('type', Notification::MENTION)->orderBy('id')->firstOrFail()->data['message_id']]);

        $this->as($this->admin())->deleteJson("/api/admin/rooms/{$room->id}/messages/{$secret->id}")->assertOk();

        $this->assertSame(1, $this->mentionsOf($sam));
        $this->assertSame(1, $this->mentionsOf($lina));
        $feed = $this->as($sam)->getJson('/api/notifications')->assertOk();
        $this->assertStringNotContainsString('behind the cinema', $feed->getContent());
        $this->assertStringContainsString('Bring popcorn', $feed->getContent());
    }

    public function test_the_new_messages_row_counts_one_fewer_and_stops_quoting_the_deleted_text(): void
    {
        [$lina, $omar, $sam] = User::factory()->count(3)->create()->all();
        $room = $this->room($lina, [$omar, $sam]);
        $this->say($omar, $room, 'First');
        $secret = $this->say($omar, $room, self::SECRET);
        $this->assertSame(2, $this->newMessages($lina)->data['count']);
        $this->assertSame(self::SECRET, $this->newMessages($lina)->data['preview']);

        $this->as($this->admin())->deleteJson("/api/admin/rooms/{$room->id}/messages/{$secret->id}")->assertOk();

        foreach ([$lina, $sam] as $member) {
            $row = $this->newMessages($member);
            $this->assertSame(1, $row->data['count']);
            $this->assertSame('', $row->data['preview']);
            $this->assertNull($row->data['message_id']);
            $feed = $this->as($member)->getJson('/api/notifications')->assertOk();
            $this->assertStringNotContainsString('behind the cinema', $feed->getContent());
            $feed->assertJsonPath('notifications.0.text', 'sent a message in')->assertJsonPath('notifications.0.detail', null);
        }
    }

    public function test_a_deleted_message_that_was_not_the_latest_keeps_the_quote_of_the_latest(): void
    {
        [$lina, $omar] = User::factory()->count(2)->create()->all();
        $room = $this->room($lina, [$omar]);
        $secret = $this->say($omar, $room, self::SECRET);
        $this->say($omar, $room, 'Latest');

        $this->as($this->admin())->deleteJson("/api/admin/rooms/{$room->id}/messages/{$secret->id}")->assertOk();

        $row = $this->newMessages($lina);
        $this->assertSame(1, $row->data['count']);
        $this->assertSame('Latest', $row->data['preview']);
    }

    public function test_the_row_goes_when_its_last_message_is_deleted_and_never_counts_below_zero(): void
    {
        [$lina, $omar] = User::factory()->count(2)->create()->all();
        $room = $this->room($lina, [$omar]);
        $only = $this->say($omar, $room, self::SECRET);
        $this->say($lina, $room, 'Reply');
        $admin = $this->admin();
        $this->assertNotNull($this->newMessages($lina));
        $this->assertSame(1, $this->newMessages($omar)->data['count']);

        $this->as($admin)->deleteJson("/api/admin/rooms/{$room->id}/messages/{$only->id}")->assertOk();

        $this->assertNull($this->newMessages($lina));
        $this->assertSame(1, $this->newMessages($omar)->data['count']);
        $this->assertSame('Reply', $this->newMessages($omar)->data['preview']);
    }

    public function test_a_member_who_already_read_the_room_keeps_their_count_but_not_the_quote(): void
    {
        [$lina, $omar, $sam] = User::factory()->count(3)->create()->all();
        $room = $this->room($lina, [$omar, $sam]);
        $first = $this->say($omar, $room, self::SECRET);
        $this->as($sam)->postJson("/api/rooms/{$room->id}/read", ['message_ids' => [$first->id]])->assertOk();
        $this->assertNotNull($this->newMessages($sam)->read_at);

        $this->as($this->admin())->deleteJson("/api/admin/rooms/{$room->id}/messages/{$first->id}")->assertOk();

        $read = $this->newMessages($sam);
        $this->assertNotNull($read->read_at);
        $this->assertSame(1, $read->data['count']);
        $this->assertSame('', $read->data['preview']);
        $this->assertNull($this->newMessages($lina));
    }

    public function test_rows_from_before_message_ids_are_matched_by_their_text_and_other_rows_are_untouched(): void
    {
        [$lina, $omar, $sam] = User::factory()->count(3)->create()->all();
        $room = $this->room($lina, [$omar, $sam]);
        $other = $this->room($lina, [$omar]);
        $secret = Message::factory()->for($room, 'room')->create(['user_id' => $omar->id, 'content' => self::SECRET]);
        $data = ['actor_id' => $omar->id, 'room_id' => $room->id, 'room_name' => $room->name];
        $oldMention = Notification::create(['user_id' => $sam->id, 'type' => Notification::MENTION, 'data' => [...$data, 'preview' => self::SECRET]]);
        $otherMention = Notification::create(['user_id' => $sam->id, 'type' => Notification::MENTION, 'data' => [...$data, 'preview' => 'Something else']]);
        $sameTextElsewhere = Notification::create(['user_id' => $sam->id, 'type' => Notification::MENTION, 'data' => ['actor_id' => $omar->id, 'room_id' => $other->id, 'room_name' => $other->name, 'preview' => self::SECRET]]);

        $this->as($this->admin())->deleteJson("/api/admin/rooms/{$room->id}/messages/{$secret->id}")->assertOk();

        $this->assertModelMissing($oldMention);
        $this->assertModelExists($otherMention);
        $this->assertModelExists($sameTextElsewhere);
    }

    public function test_a_member_who_is_not_waiting_on_the_message_keeps_their_count(): void
    {
        [$lina, $omar] = User::factory()->count(2)->create()->all();
        $room = $this->room($lina, [$omar]);
        $this->say($omar, $room, 'First');
        $row = $this->newMessages($lina);
        $this->as($lina)->postJson("/api/rooms/{$room->id}/read", ['message_ids' => [Message::firstOrFail()->id]])->assertOk();
        $secret = $this->say($omar, $room, self::SECRET);
        $this->assertSame(1, $this->newMessages($lina)->fresh()->data['count']);
        $this->assertSame($row->id, $this->newMessages($lina)->id);

        $other = $this->say($lina, $room, 'Mine');
        $this->as($this->admin())->deleteJson("/api/admin/rooms/{$room->id}/messages/{$other->id}")->assertOk();

        $this->assertSame(1, $this->newMessages($lina)->data['count']);
        $this->assertSame(self::SECRET, $this->newMessages($lina)->data['preview']);
        $this->assertSame($secret->id, $this->newMessages($lina)->data['message_id']);
    }

    public function test_a_staff_room_deletion_shows_the_team_to_members_and_never_the_staff_member(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Alexandra Staffer', 'username' => 'alexstaff']);
        [$lina, $omar, $sam] = User::factory()->count(3)->create()->all();
        $room = $this->room($lina, [$omar], ['is_private' => true]);
        DB::table('room_invites')->insert(['room_id' => $room->id, 'user_id' => $sam->id, 'invited_by' => $lina->id]);

        $this->as($admin)->deleteJson("/api/admin/rooms/{$room->id}")->assertOk();

        foreach ([$omar, $sam] as $member) {
            $feed = $this->as($member)->getJson('/api/notifications')->assertOk()
                ->assertJsonCount(1, 'notifications')
                ->assertJsonPath('notifications.0.kind', 'room_deleted')
                ->assertJsonPath('notifications.0.actor', ['id' => 0, 'username' => 'watchwise', 'name' => 'WatchWise', 'avatar' => null])
                ->assertJsonPath('notifications.0.text', 'deleted the room')
                ->assertJsonPath('notifications.0.target', 'Thriller Thursday');
            $this->assertStringNotContainsString('alexstaff', $feed->getContent());
            $this->assertStringNotContainsString('Alexandra', $feed->getContent());
        }
        $creator = $this->as($lina)->getJson('/api/notifications')->assertOk()
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.kind', 'room_removed')
            ->assertJsonPath('notifications.0.actor.id', 0)
            ->assertJsonPath('notifications.0.text', 'deleted your room')
            ->assertJsonPath('notifications.0.target', 'Thriller Thursday')
            ->assertJsonPath('notifications.0.film', ['tmdb_id' => 550, 'media_type' => 'movie'])
            ->assertJsonPath('notifications.0.to', '/rooms');
        $this->assertStringNotContainsString('alexstaff', $creator->getContent());
        $this->assertSame(0, Notification::where('user_id', $admin->id)->count());
        $this->assertSame(3, Notification::count());
    }

    public function test_the_creator_hears_of_a_staff_deletion_even_with_room_deleted_switched_off_but_members_may_opt_out(): void
    {
        [$lina, $omar] = User::factory()->count(2)->create()->all();
        $room = $this->room($lina, [$omar]);
        foreach ([$lina, $omar] as $member) {
            $this->as($member)->patchJson('/api/me/preferences', ['notifications' => ['room_deleted' => false]])->assertOk();
        }

        $this->as($this->admin())->deleteJson("/api/admin/rooms/{$room->id}")->assertOk();

        $this->assertSame([Notification::ROOM_REMOVED], Notification::where('user_id', $lina->id)->pluck('type')->all());
        $this->assertSame(0, Notification::where('user_id', $omar->id)->count());
        $this->as($lina)->getJson('/api/me')->assertJsonPath('counts.unread_notifications', 1);
    }

    public function test_a_staff_member_deleting_their_own_room_is_told_nothing_and_the_member_route_still_names_the_deleter(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Alexandra Staffer', 'username' => 'alexstaff']);
        [$lina, $omar] = User::factory()->count(2)->create()->all();
        $own = $this->room($admin, [$omar]);
        $theirs = $this->room($lina, [$omar]);

        $this->as($admin)->deleteJson("/api/admin/rooms/{$own->id}")->assertOk();

        $this->assertSame(0, Notification::where('user_id', $admin->id)->count());
        $this->assertSame([Notification::ROOM_DELETED], Notification::where('user_id', $omar->id)->pluck('type')->all());
        $this->assertArrayNotHasKey('actor_id', Notification::where('user_id', $omar->id)->firstOrFail()->data);

        $this->as($admin)->deleteJson("/api/rooms/{$theirs->id}")->assertOk();

        $this->as($omar)->getJson('/api/notifications')->assertOk()
            ->assertJsonPath('notifications.0.actor.username', 'alexstaff')
            ->assertJsonPath('notifications.0.text', 'deleted the room');
        $this->assertSame([Notification::ROOM_DELETED], Notification::where('user_id', $lina->id)->pluck('type')->all());
    }

    public function test_the_report_queue_still_shows_the_first_200_characters_of_a_reported_message_after_it_is_gone(): void
    {
        [$lina, $omar] = User::factory()->count(2)->create()->all();
        $room = $this->room($lina, [$omar]);
        $long = str_repeat('x', 150).self::SECRET.str_repeat('y', 100);
        $message = $this->say($omar, $room, $long);
        $this->as($lina)->postJson("/api/rooms/{$room->id}/messages/{$message->id}/report", ['reason' => 'harassment'])->assertCreated();
        $admin = $this->admin();

        $this->as($admin)->deleteJson("/api/admin/rooms/{$room->id}/messages/{$message->id}")->assertOk();

        $this->as($admin)->getJson('/api/admin/reports?status=all')->assertOk()
            ->assertJsonPath('data.0.status', 'resolved')
            ->assertJsonPath('data.0.target.removed', true)
            ->assertJsonPath('data.0.target.excerpt', mb_substr($long, 0, 200));
    }
}
