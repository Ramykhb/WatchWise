<?php

namespace Tests\Feature\Room;

use App\Models\DiscussionRoom;
use App\Models\Notification;
use App\Models\User;
use App\Room\Events\RoomMessageSent;
use App\Room\Events\RoomMessagesRead;
use App\Room\Events\RoomsChanged;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Social\SocialTestCase;

class RoomMembershipTest extends SocialTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([RoomMessageSent::class, RoomMessagesRead::class, RoomsChanged::class]);
    }

    /**
     * A room hosted by `$host`, with `$members` in it and `$invitees` invited by the host.
     *
     * @param  list<User>  $members
     * @param  list<User>  $invitees
     */
    private function room(User $host, bool $private, array $members = [], array $invitees = []): DiscussionRoom
    {
        $room = DiscussionRoom::create(['tmdb_id' => 1003, 'name' => 'Thriller Thursday', 'created_by' => $host->id, 'is_private' => $private]);
        foreach ([$host, ...$members] as $member) {
            DB::table('room_members')->insert(['room_id' => $room->id, 'user_id' => $member->id]);
        }
        foreach ($invitees as $invitee) {
            DB::table('room_invites')->insert(['room_id' => $room->id, 'user_id' => $invitee->id, 'invited_by' => $host->id]);
        }

        return $room;
    }

    private function send(User $sender, DiscussionRoom $room): int
    {
        return $this->as($sender)->postJson("/api/rooms/{$room->id}/messages", ['content' => 'Deep Blue tonight?'])->assertCreated()->json('message.id');
    }

    /**
     * @return list<string>
     */
    private function channelNames(RoomMessageSent|RoomsChanged $event): array
    {
        return array_map(fn (PrivateChannel $channel): string => $channel->name, $event->broadcastOn());
    }

    public function test_creating_a_private_room_keeps_it_out_of_everyone_elses_list(): void
    {
        [$maya, $omar] = User::factory()->count(2)->create()->all();

        $id = $this->as($maya)->postJson('/api/rooms', ['name' => 'Spoiler club', 'tmdb_id' => 1007, 'is_private' => true])
            ->assertCreated()
            ->assertJsonPath('room.is_private', true)
            ->assertJsonPath('room.joined', true)
            ->json('room.id');

        $this->assertDatabaseHas('discussion_rooms', ['id' => $id, 'is_private' => true]);
        $this->as($omar)->getJson('/api/rooms')->assertOk()->assertJsonCount(0, 'rooms');
        Event::assertNotDispatched(RoomsChanged::class);
    }

    public function test_creating_a_public_room_pushes_it_to_every_rooms_list(): void
    {
        $maya = User::factory()->create();

        $id = $this->as($maya)->postJson('/api/rooms', ['name' => 'Oscars 2026 picks', 'tmdb_id' => 1007])
            ->assertCreated()
            ->assertJsonPath('room.is_private', false)
            ->json('room.id');

        Event::assertDispatched(RoomsChanged::class, fn (RoomsChanged $event): bool => $event->roomId === $id
            && $this->channelNames($event) === ['private-rooms.public']);
    }

    public function test_an_outsider_gets_403_from_a_private_room_and_never_sees_it_listed(): void
    {
        [$lina, $outsider] = User::factory()->count(2)->create()->all();
        $room = $this->room($lina, private: true);

        $this->as($outsider)->getJson("/api/rooms/{$room->id}/messages")->assertForbidden();
        $this->as($outsider)->postJson("/api/rooms/{$room->id}/join")->assertForbidden();
        $this->as($outsider)->postJson("/api/rooms/{$room->id}/read", ['message_ids' => [1]])->assertForbidden();
        $this->as($outsider)->getJson('/api/rooms')->assertOk()->assertJsonCount(0, 'rooms');

        $this->assertDatabaseMissing('room_members', ['room_id' => $room->id, 'user_id' => $outsider->id]);
    }

    public function test_anyone_can_read_a_public_room_without_joining(): void
    {
        [$lina, $omar, $visitor] = User::factory()->count(3)->create()->all();
        $room = $this->room($lina, private: false, members: [$omar]);
        $id = $this->send($lina, $room);

        $this->as($visitor)->getJson("/api/rooms/{$room->id}/messages")
            ->assertOk()
            ->assertJsonPath('messages.0.id', $id)
            ->assertJsonCount(1, 'messages.0.unread_by')
            ->assertJsonPath('messages.0.unread_by.0.id', $omar->id);
    }

    public function test_a_visitors_read_confirmation_does_not_count(): void
    {
        [$lina, $omar, $visitor] = User::factory()->count(3)->create()->all();
        $room = $this->room($lina, private: false, members: [$omar]);
        $id = $this->send($lina, $room);

        $this->as($visitor)->postJson("/api/rooms/{$room->id}/read", ['message_ids' => [$id]])
            ->assertOk()
            ->assertExactJson(['message_ids' => [], 'deleted_ids' => []]);

        $this->assertDatabaseHas('messages', ['id' => $id]);
        $this->assertSame(1, Notification::where('type', Notification::ROOM_MESSAGE)->where('user_id', $omar->id)->whereNull('read_at')->count());
        Event::assertNotDispatched(RoomMessagesRead::class);
    }

    public function test_joining_a_public_room_makes_you_a_member_and_updates_every_rooms_list(): void
    {
        [$host, $visitor] = User::factory()->count(2)->create()->all();
        $room = $this->room($host, private: false);

        $this->as($visitor)->postJson("/api/rooms/{$room->id}/join")
            ->assertOk()
            ->assertJsonPath('room.joined', true)
            ->assertJsonCount(2, 'room.members');

        $this->assertDatabaseHas('room_members', ['room_id' => $room->id, 'user_id' => $visitor->id]);
        Event::assertDispatched(RoomsChanged::class, fn (RoomsChanged $event): bool => $event->roomId === $room->id
            && $this->channelNames($event) === ['private-rooms.public']);
    }

    public function test_a_member_invites_people_to_a_private_room(): void
    {
        [$lina, $omar] = User::factory()->count(2)->create()->all();
        $room = $this->room($lina, private: true);

        $this->as($lina)->postJson("/api/rooms/{$room->id}/invites", ['invitees' => [$omar->username]])
            ->assertOk()
            ->assertJsonCount(1, 'room.invitees')
            ->assertJsonPath('room.invitees.0.id', $omar->id);

        $this->assertDatabaseHas('room_invites', ['room_id' => $room->id, 'user_id' => $omar->id, 'invited_by' => $lina->id]);
        $this->assertDatabaseMissing('room_members', ['room_id' => $room->id, 'user_id' => $omar->id]);
        Event::assertDispatched(RoomsChanged::class, fn (RoomsChanged $event): bool => $event->roomId === $room->id
            && $this->channelNames($event) === ["private-rooms.user.{$lina->id}", "private-rooms.user.{$omar->id}"]);
    }

    public function test_inviting_skips_members_and_people_already_invited(): void
    {
        [$lina, $omar, $sam, $nour] = User::factory()->count(4)->create()->all();
        $room = $this->room($lina, private: true, members: [$omar], invitees: [$sam]);

        $this->as($omar)->postJson("/api/rooms/{$room->id}/invites", ['invitees' => [$lina->username, $sam->username, $nour->username]])
            ->assertOk()
            ->assertJsonCount(2, 'room.invitees');

        $this->assertDatabaseHas('room_invites', ['room_id' => $room->id, 'user_id' => $sam->id, 'invited_by' => $lina->id]);
        $this->assertDatabaseHas('room_invites', ['room_id' => $room->id, 'user_id' => $nour->id, 'invited_by' => $omar->id]);
        $this->assertDatabaseMissing('room_invites', ['room_id' => $room->id, 'user_id' => $lina->id]);
    }

    public function test_an_invitee_cannot_invite_others_and_gets_403(): void
    {
        [$lina, $omar, $sam] = User::factory()->count(3)->create()->all();
        $room = $this->room($lina, private: true, invitees: [$omar]);

        $this->as($omar)->postJson("/api/rooms/{$room->id}/invites", ['invitees' => [$sam->username]])->assertForbidden();

        $this->assertDatabaseMissing('room_invites', ['room_id' => $room->id, 'user_id' => $sam->id]);
        Event::assertNotDispatched(RoomsChanged::class);
    }

    public function test_invitations_need_existing_usernames(): void
    {
        $lina = User::factory()->create();
        $room = $this->room($lina, private: true);

        $this->as($lina)->postJson("/api/rooms/{$room->id}/invites", ['invitees' => ['nobody-here']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('invitees.0');
        $this->as($lina)->postJson("/api/rooms/{$room->id}/invites", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('invitees');

        $this->assertDatabaseCount('room_invites', 0);
    }

    public function test_an_invitee_sees_the_private_room_with_who_invited_them(): void
    {
        [$lina, $omar] = User::factory()->count(2)->create()->all();
        $room = $this->room($lina, private: true, invitees: [$omar]);

        $this->as($omar)->getJson('/api/rooms')
            ->assertOk()
            ->assertJsonPath('rooms.0.id', $room->id)
            ->assertJsonPath('rooms.0.is_private', true)
            ->assertJsonPath('rooms.0.joined', false)
            ->assertJsonPath('rooms.0.invited', true)
            ->assertJsonPath('rooms.0.invited_by.id', $lina->id);
    }

    public function test_an_invitee_can_read_a_private_room_before_joining(): void
    {
        [$lina, $omar, $sam] = User::factory()->count(3)->create()->all();
        $room = $this->room($lina, private: true, members: [$sam], invitees: [$omar]);
        $id = $this->send($lina, $room);

        $this->as($omar)->getJson("/api/rooms/{$room->id}/messages")
            ->assertOk()
            ->assertJsonPath('messages.0.id', $id)
            ->assertJsonCount(1, 'messages.0.unread_by')
            ->assertJsonPath('messages.0.unread_by.0.id', $sam->id);
    }

    public function test_an_invitee_must_join_before_posting_and_gets_403(): void
    {
        [$lina, $omar] = User::factory()->count(2)->create()->all();
        $room = $this->room($lina, private: true, invitees: [$omar]);

        $this->as($omar)->postJson("/api/rooms/{$room->id}/messages", ['content' => 'Hi!'])->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_accepting_an_invitation_turns_it_into_membership(): void
    {
        [$lina, $omar] = User::factory()->count(2)->create()->all();
        $room = $this->room($lina, private: true, invitees: [$omar]);

        $this->as($omar)->postJson("/api/rooms/{$room->id}/join")
            ->assertOk()
            ->assertJsonPath('room.joined', true)
            ->assertJsonPath('room.invited', false)
            ->assertJsonCount(0, 'room.invitees');

        $this->assertDatabaseHas('room_members', ['room_id' => $room->id, 'user_id' => $omar->id]);
        $this->assertDatabaseMissing('room_invites', ['room_id' => $room->id, 'user_id' => $omar->id]);
        Event::assertDispatched(RoomsChanged::class, fn (RoomsChanged $event): bool => $event->roomId === $room->id
            && $this->channelNames($event) === ["private-rooms.user.{$lina->id}", "private-rooms.user.{$omar->id}"]);
    }

    public function test_declining_an_invitation_removes_it_and_tells_the_decliners_list_too(): void
    {
        [$lina, $omar] = User::factory()->count(2)->create()->all();
        $room = $this->room($lina, private: true, invitees: [$omar]);

        $this->as($omar)->deleteJson("/api/rooms/{$room->id}/invite")
            ->assertOk()
            ->assertExactJson(['message' => 'Invitation declined.']);

        $this->assertDatabaseMissing('room_invites', ['room_id' => $room->id, 'user_id' => $omar->id]);
        $this->assertDatabaseMissing('room_members', ['room_id' => $room->id, 'user_id' => $omar->id]);
        Event::assertDispatched(RoomsChanged::class, fn (RoomsChanged $event): bool => $event->roomId === $room->id
            && $this->channelNames($event) === ["private-rooms.user.{$lina->id}", "private-rooms.user.{$omar->id}"]);
    }

    public function test_declining_without_an_invitation_returns_403(): void
    {
        [$lina, $omar] = User::factory()->count(2)->create()->all();
        $room = $this->room($lina, private: true, members: [$omar]);

        $this->as($omar)->deleteJson("/api/rooms/{$room->id}/invite")->assertForbidden();

        $this->assertDatabaseHas('room_members', ['room_id' => $room->id, 'user_id' => $omar->id]);
        Event::assertNotDispatched(RoomsChanged::class);
    }

    public function test_a_private_rooms_new_message_reaches_the_lists_of_its_members_and_invitees_only(): void
    {
        [$lina, $omar, $sam] = User::factory()->count(3)->create()->all();
        $room = $this->room($lina, private: true, members: [$omar], invitees: [$sam]);

        $this->send($lina, $room);

        Event::assertDispatched(RoomMessageSent::class, fn (RoomMessageSent $event): bool => $this->channelNames($event) === [
            "private-room.{$room->id}",
            "private-rooms.user.{$lina->id}",
            "private-rooms.user.{$omar->id}",
            "private-rooms.user.{$sam->id}",
        ]);
    }

    public function test_a_public_rooms_new_message_reaches_every_rooms_list(): void
    {
        [$lina, $omar] = User::factory()->count(2)->create()->all();
        $room = $this->room($lina, private: false, members: [$omar]);

        $this->send($lina, $room);

        Event::assertDispatched(RoomMessageSent::class, fn (RoomMessageSent $event): bool => $this->channelNames($event) === [
            "private-room.{$room->id}",
            'private-rooms.public',
        ]);
    }

    public function test_socket_channels_are_signed_only_for_people_allowed_to_listen(): void
    {
        config([
            'broadcasting.connections.reverb.key' => 'test-socket-key',
            'broadcasting.connections.reverb.secret' => 'test-socket-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        [$lina, $omar, $outsider] = User::factory()->count(3)->create()->all();
        $room = $this->room($lina, private: true, invitees: [$omar]);
        $auth = fn (User $user, string $channel) => $this->as($user)->postJson('/api/rooms/socket-auth', ['socket_id' => '123.456', 'channel_name' => $channel]);

        $auth($omar, "private-room.{$room->id}")->assertOk();
        $auth($omar, 'private-rooms.public')->assertOk();
        $auth($omar, "private-rooms.user.{$omar->id}")
            ->assertOk()
            ->assertExactJson(['auth' => 'test-socket-key:'.hash_hmac('sha256', "123.456:private-rooms.user.{$omar->id}", 'test-socket-secret')]);
        $auth($omar, "private-rooms.user.{$lina->id}")->assertForbidden();
        $auth($outsider, "private-room.{$room->id}")->assertForbidden();
    }

    public function test_home_leaves_out_private_rooms_you_are_not_in(): void
    {
        [$lina, $omar] = User::factory()->count(2)->create()->all();
        $this->room($lina, private: true);
        $public = $this->room($lina, private: false);

        $this->as($omar)->getJson('/api/home')
            ->assertOk()
            ->assertJsonCount(1, 'rooms')
            ->assertJsonPath('rooms.0.id', $public->id);
    }

    public function test_home_shows_the_private_rooms_you_are_in(): void
    {
        [$lina, $omar] = User::factory()->count(2)->create()->all();
        $room = $this->room($lina, private: true, members: [$omar]);

        $this->as($omar)->getJson('/api/home')
            ->assertOk()
            ->assertJsonCount(1, 'rooms')
            ->assertJsonPath('rooms.0.id', $room->id);
    }
}
