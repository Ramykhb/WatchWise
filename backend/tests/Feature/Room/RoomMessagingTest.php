<?php

namespace Tests\Feature\Room;

use App\Models\DiscussionRoom;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use App\Room\Events\RoomMessageSent;
use App\Room\Events\RoomMessagesRead;
use App\Room\Events\RoomsChanged;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Social\SocialTestCase;

class RoomMessagingTest extends SocialTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([RoomMessageSent::class, RoomMessagesRead::class, RoomsChanged::class]);
    }

    /**
     * A room hosted by `$host`, with `$others` as members too.
     *
     * @param  list<User>  $others
     */
    private function room(User $host, array $others = []): DiscussionRoom
    {
        $room = DiscussionRoom::create(['tmdb_id' => 1003, 'name' => 'Thriller Thursday', 'created_by' => $host->id]);
        foreach ([$host, ...$others] as $member) {
            DB::table('room_members')->insert(['room_id' => $room->id, 'user_id' => $member->id]);
        }

        return $room;
    }

    private function send(User $sender, DiscussionRoom $room, string $content = 'Deep Blue tonight?'): int
    {
        return $this->as($sender)->postJson("/api/rooms/{$room->id}/messages", ['content' => $content])->assertCreated()->json('message.id');
    }

    /**
     * @return Builder<Notification>
     */
    private function receipts(int $messageId): Builder
    {
        return Notification::where('type', Notification::ROOM_MESSAGE)->where('data->message_id', $messageId);
    }

    public function test_a_message_waits_for_every_other_member_and_is_pushed_to_the_room(): void
    {
        [$lina, $omar, $sam] = User::factory()->count(3)->create()->all();
        $room = $this->room($lina, [$omar, $sam]);

        $id = $this->as($lina)->postJson("/api/rooms/{$room->id}/messages", ['content' => 'Deep Blue tonight?'])
            ->assertCreated()
            ->assertJsonPath('message.content', 'Deep Blue tonight?')
            ->assertJsonPath('message.user.id', $lina->id)
            ->assertJsonCount(1, 'message.read_by')
            ->assertJsonPath('message.read_by.0.id', $lina->id)
            ->assertJsonCount(2, 'message.unread_by')
            ->json('message.id');

        $this->assertDatabaseHas('messages', ['id' => $id, 'room_id' => $room->id]);
        $this->assertEqualsCanonicalizing([$omar->id, $sam->id], $this->receipts($id)->whereNull('read_at')->pluck('user_id')->all());
        Event::assertDispatched(RoomMessageSent::class, fn (RoomMessageSent $event): bool => $event->broadcastOn()[0]->name === "private-room.{$room->id}"
            && $event->broadcastAs() === 'message.sent'
            && $event->broadcastWith()['id'] === $id);
    }

    public function test_a_message_is_deleted_once_every_member_has_read_it(): void
    {
        [$lina, $omar, $sam] = User::factory()->count(3)->create()->all();
        $room = $this->room($lina, [$omar, $sam]);
        $id = $this->send($lina, $room);

        $this->as($omar)->postJson("/api/rooms/{$room->id}/read", ['message_ids' => [$id]])
            ->assertOk()
            ->assertExactJson(['message_ids' => [$id], 'deleted_ids' => []]);
        $this->assertDatabaseHas('messages', ['id' => $id]);

        // Sam opens the room: Lina's message shows it has been read by Lina and Omar, and waits for Sam.
        $this->as($sam)->getJson("/api/rooms/{$room->id}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.read_by.0.id', $lina->id)
            ->assertJsonPath('messages.0.read_by.1.id', $omar->id)
            ->assertJsonCount(1, 'messages.0.unread_by')
            ->assertJsonPath('messages.0.unread_by.0.id', $sam->id);

        $this->as($sam)->postJson("/api/rooms/{$room->id}/read", ['message_ids' => [$id]])
            ->assertOk()
            ->assertExactJson(['message_ids' => [$id], 'deleted_ids' => [$id]]);

        $this->assertDatabaseMissing('messages', ['id' => $id]);
        $this->assertSame(0, $this->receipts($id)->count());
        Event::assertDispatched(RoomMessagesRead::class, fn (RoomMessagesRead $event): bool => $event->reader->is($sam)
            && $event->broadcastOn()[0]->name === "private-room.{$room->id}"
            && $event->broadcastWith()['deleted_ids'] === [$id]);
    }

    public function test_opening_a_room_does_not_mark_anything_read_on_its_own(): void
    {
        [$lina, $omar] = User::factory()->count(2)->create()->all();
        $room = $this->room($lina, [$omar]);
        $id = $this->send($lina, $room);

        $this->as($omar)->getJson("/api/rooms/{$room->id}/messages")->assertOk()->assertJsonCount(1, 'messages');

        $this->assertSame(1, $this->receipts($id)->whereNull('read_at')->count());
    }

    public function test_a_message_nobody_else_has_to_read_is_not_kept(): void
    {
        $host = User::factory()->create();
        $room = $this->room($host);

        $this->as($host)->postJson("/api/rooms/{$room->id}/messages", ['content' => 'Anyone here?'])
            ->assertCreated()
            ->assertJsonCount(0, 'message.unread_by');

        $this->assertDatabaseCount('messages', 0);
        Event::assertDispatched(RoomMessageSent::class);
    }

    public function test_posting_without_joining_returns_403_and_does_not_make_you_a_member(): void
    {
        [$host, $visitor] = User::factory()->count(2)->create()->all();
        $room = $this->room($host);

        $this->as($visitor)->postJson("/api/rooms/{$room->id}/messages", ['content' => 'Hi all!'])->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseMissing('room_members', ['room_id' => $room->id, 'user_id' => $visitor->id]);
        Event::assertNotDispatched(RoomMessageSent::class);
    }

    public function test_a_member_who_joins_later_does_not_hold_up_older_messages(): void
    {
        [$lina, $omar, $late] = User::factory()->count(3)->create()->all();
        $room = $this->room($lina, [$omar]);
        $id = $this->send($lina, $room);
        $this->as($late)->postJson("/api/rooms/{$room->id}/join")->assertOk();
        $this->send($late, $room, 'Just joined!');

        $this->as($omar)->postJson("/api/rooms/{$room->id}/read", ['message_ids' => [$id]])
            ->assertOk()
            ->assertJsonPath('deleted_ids', [$id]);
    }

    public function test_reading_only_touches_the_readers_own_pending_receipts_in_that_room(): void
    {
        [$lina, $omar] = User::factory()->count(2)->create()->all();
        $room = $this->room($lina, [$omar]);
        $other = $this->room($lina, [$omar]);
        $mine = $this->send($lina, $room);
        $elsewhere = $this->send($lina, $other);

        // The author has nothing to confirm, and a message of another room is ignored.
        $this->as($lina)->postJson("/api/rooms/{$room->id}/read", ['message_ids' => [$mine]])
            ->assertExactJson(['message_ids' => [], 'deleted_ids' => []]);
        $this->as($omar)->postJson("/api/rooms/{$room->id}/read", ['message_ids' => [$elsewhere]])
            ->assertExactJson(['message_ids' => [], 'deleted_ids' => []]);

        $this->assertDatabaseHas('messages', ['id' => $mine]);
        $this->assertDatabaseHas('messages', ['id' => $elsewhere]);
        Event::assertNotDispatched(RoomMessagesRead::class);
    }

    public function test_messages_from_before_read_tracking_wait_for_the_other_members(): void
    {
        [$jad, $lina, $maya] = User::factory()->count(3)->create()->all();
        $room = $this->room($jad, [$lina, $maya]);
        $old = Message::create(['room_id' => $room->id, 'user_id' => $jad->id, 'content' => 'Ending explained?']);

        $this->as($maya)->getJson("/api/rooms/{$room->id}/messages")
            ->assertOk()
            ->assertJsonPath('messages.0.id', $old->id)
            ->assertJsonCount(1, 'messages.0.read_by')
            ->assertJsonCount(2, 'messages.0.unread_by');

        $this->assertEqualsCanonicalizing([$lina->id, $maya->id], $this->receipts($old->id)->whereNull('read_at')->pluck('user_id')->all());
    }

    public function test_a_message_nobody_is_waiting_on_any_more_is_cleaned_up_when_the_room_is_opened(): void
    {
        [$lina, $omar, $sam] = User::factory()->count(3)->create()->all();
        $room = $this->room($lina, [$omar, $sam]);
        $id = $this->send($lina, $room);
        $this->as($omar)->postJson("/api/rooms/{$room->id}/read", ['message_ids' => [$id]])->assertOk();

        // The only member still to read it deletes their account (their receipt goes with it).
        $sam->delete();

        $this->as($lina)->getJson("/api/rooms/{$room->id}/messages")->assertOk()->assertJsonCount(0, 'messages');
        $this->assertDatabaseMissing('messages', ['id' => $id]);
        $this->assertSame(0, $this->receipts($id)->count());
    }

    public function test_the_room_list_puts_your_rooms_first_with_what_waits_for_you(): void
    {
        [$lina, $omar] = User::factory()->count(2)->create()->all();
        $theirs = $this->room($omar);
        $ours = $this->room($lina, [$omar]);
        $this->send($omar, $ours, 'First');
        $this->send($omar, $ours, 'Second');

        $this->as($lina)->getJson('/api/rooms')
            ->assertOk()
            ->assertJsonPath('rooms.0.id', $ours->id)
            ->assertJsonPath('rooms.0.joined', true)
            ->assertJsonPath('rooms.0.host.id', $lina->id)
            ->assertJsonCount(2, 'rooms.0.members')
            ->assertJsonPath('rooms.0.unread', 2)
            ->assertJsonPath('rooms.0.message_count', 2)
            ->assertJsonPath('rooms.0.last_message.content', 'Second')
            ->assertJsonPath('rooms.1.id', $theirs->id)
            ->assertJsonPath('rooms.1.joined', false)
            ->assertJsonPath('rooms.1.unread', 0);
    }

    public function test_creating_a_room_makes_the_creator_its_first_member(): void
    {
        $maya = User::factory()->create();

        $this->as($maya)->postJson('/api/rooms', ['name' => 'Oscars 2026 picks', 'tmdb_id' => 1007, 'spoilers' => true])
            ->assertCreated()
            ->assertJsonPath('room.name', 'Oscars 2026 picks')
            ->assertJsonPath('room.spoilers', true)
            ->assertJsonPath('room.joined', true)
            ->assertJsonPath('room.host.id', $maya->id)
            ->assertJsonPath('room.members.0.id', $maya->id);

        $this->as($maya)->postJson('/api/rooms', ['name' => 'x', 'tmdb_id' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'tmdb_id']);
    }

    public function test_messages_and_read_receipts_are_validated(): void
    {
        $lina = User::factory()->create();
        $room = $this->room($lina);

        $this->as($lina)->postJson("/api/rooms/{$room->id}/messages", ['content' => ''])->assertUnprocessable()->assertJsonValidationErrors('content');
        $this->as($lina)->postJson("/api/rooms/{$room->id}/messages", ['content' => str_repeat('a', 1001)])->assertUnprocessable();
        $this->as($lina)->postJson("/api/rooms/{$room->id}/read", ['message_ids' => []])->assertUnprocessable()->assertJsonValidationErrors('message_ids');
        $this->as($lina)->postJson("/api/rooms/{$room->id}/read", ['message_ids' => ['abc']])->assertUnprocessable();
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_rooms_need_a_signed_in_member_and_an_existing_room(): void
    {
        $this->getJson('/api/rooms')->assertUnauthorized();
        $this->postJson('/api/rooms/1/messages', ['content' => 'Hi'])->assertUnauthorized();
        $this->postJson('/api/rooms/1/read', ['message_ids' => [1]])->assertUnauthorized();

        $this->as(User::factory()->create())->getJson('/api/rooms/999/messages')->assertNotFound();
    }

    public function test_the_socket_channel_is_signed_for_existing_rooms_only(): void
    {
        config([
            'broadcasting.connections.reverb.key' => 'test-socket-key',
            'broadcasting.connections.reverb.secret' => 'test-socket-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        $user = User::factory()->create();
        $room = $this->room($user);
        $channel = "private-room.{$room->id}";

        $this->as($user)->postJson('/api/rooms/socket-auth', ['socket_id' => '123.456', 'channel_name' => $channel])
            ->assertOk()
            ->assertExactJson(['auth' => 'test-socket-key:'.hash_hmac('sha256', "123.456:{$channel}", 'test-socket-secret')]);

        $this->as($user)->postJson('/api/rooms/socket-auth', ['socket_id' => '123.456', 'channel_name' => 'private-room.999'])->assertForbidden();
        $this->as($user)->postJson('/api/rooms/socket-auth', ['socket_id' => '123.456', 'channel_name' => 'private-chat.1.abc'])->assertUnprocessable();

        $this->withHeaders(['Authorization' => ''])->postJson('/api/rooms/socket-auth', ['socket_id' => '123.456', 'channel_name' => $channel])->assertUnauthorized();
    }

    public function test_existing_rooms_keep_their_creator_and_posters_as_members(): void
    {
        [$jad, $lina, $maya] = User::factory()->count(3)->create()->all();
        $room = DiscussionRoom::create(['tmdb_id' => 1001, 'name' => 'Neon Tide — ending explained', 'created_by' => $jad->id]);
        Message::create(['room_id' => $room->id, 'user_id' => $lina->id, 'content' => 'Replaying!']);
        Message::create(['room_id' => $room->id, 'user_id' => $lina->id, 'content' => 'Cooler palette.']);
        Message::create(['room_id' => $room->id, 'user_id' => $jad->id, 'content' => 'Was she back in 2019?']);

        $migration = require database_path('migrations/2026_10_26_000001_create_room_members_table.php');
        $migration->down();
        $migration->up();

        $this->assertEqualsCanonicalizing([$jad->id, $lina->id], DB::table('room_members')->where('room_id', $room->id)->pluck('user_id')->all());
        $this->assertNotContains($maya->id, DB::table('room_members')->pluck('user_id')->all());
    }

    public function test_messages_waiting_to_be_read_are_not_counted_as_notifications(): void
    {
        [$lina, $omar] = User::factory()->count(2)->create()->all();
        $this->send($lina, $this->room($lina, [$omar]));
        Notification::create(['user_id' => $omar->id, 'type' => 'follow', 'data' => ['user_id' => $lina->id]]);

        $this->as($omar)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('counts.unread_notifications', 1);
    }

    public function test_home_counts_room_members(): void
    {
        [$lina, $omar, $sam] = User::factory()->count(3)->create()->all();
        $room = $this->room($lina, [$omar, $sam]);
        $this->send($lina, $room);

        $this->as($omar)->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('rooms.0.id', $room->id)
            ->assertJsonPath('rooms.0.members', 3);
    }
}
