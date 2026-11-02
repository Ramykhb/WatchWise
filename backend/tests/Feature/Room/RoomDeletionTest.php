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

class RoomDeletionTest extends SocialTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([RoomMessageSent::class, RoomMessagesRead::class, RoomsChanged::class, ...self::NOTIFICATION_EVENTS]);
    }

    /**
     * A room created by `$host`, with `$members` in it and `$invitees` invited by the host.
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

    /**
     * @return list<string>
     */
    private function channelNames(RoomsChanged $event): array
    {
        return array_map(fn (PrivateChannel $channel): string => $channel->name, $event->broadcastOn());
    }

    public function test_the_creator_deletes_the_room_with_everything_in_it(): void
    {
        [$lina, $omar, $sam] = User::factory()->count(3)->create()->all();
        $room = $this->room($lina, private: true, members: [$omar], invitees: [$sam]);
        $this->as($lina)->postJson("/api/rooms/{$room->id}/messages", ['content' => "@{$omar->username} Deep Blue tonight?"])->assertCreated();
        Notification::create(['user_id' => $sam->id, 'type' => Notification::ROOM_INVITE, 'data' => ['actor_id' => $lina->id, 'room_id' => $room->id, 'room_name' => $room->name]]);

        $this->as($lina)->deleteJson("/api/rooms/{$room->id}")->assertOk()->assertJsonPath('message', 'Room deleted.');

        $this->assertModelMissing($room);
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('room_members', 0);
        $this->assertDatabaseCount('room_invites', 0);
        // The read receipts and every notification that opened the room are gone; members and invitees are told
        // who deleted it, and the person who did is not.
        $this->assertSame([], Notification::where('user_id', $lina->id)->pluck('type')->all());
        $this->assertSame([Notification::ROOM_DELETED], Notification::where('user_id', $omar->id)->pluck('type')->all());
        $this->assertSame([Notification::ROOM_DELETED], Notification::where('user_id', $sam->id)->pluck('type')->all());
        $this->as($omar)->getJson('/api/notifications')
            ->assertJsonPath('notifications.0.actor.username', $lina->username)
            ->assertJsonPath('notifications.0.text', 'deleted the room')
            ->assertJsonPath('notifications.0.target', 'Thriller Thursday')
            ->assertJsonPath('notifications.0.to', '/rooms');
        // Every list that showed the private room drops it.
        Event::assertDispatched(RoomsChanged::class, fn (RoomsChanged $event): bool => $event->roomId === $room->id
            && $this->channelNames($event) === ["private-rooms.user.{$lina->id}", "private-rooms.user.{$omar->id}", "private-rooms.user.{$sam->id}"]);
    }

    public function test_an_admin_can_delete_any_room(): void
    {
        $host = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $room = $this->room($host, private: false);

        $this->as($admin)->deleteJson("/api/rooms/{$room->id}")->assertOk();

        $this->assertModelMissing($room);
        $this->assertSame([Notification::ROOM_DELETED], Notification::where('user_id', $host->id)->pluck('type')->all());
        Event::assertDispatched(RoomsChanged::class, fn (RoomsChanged $event): bool => $this->channelNames($event) === ['private-rooms.public']);
    }

    public function test_members_moderators_and_strangers_cannot_delete_a_room(): void
    {
        [$host, $member, $stranger] = User::factory()->count(3)->create()->all();
        $moderator = User::factory()->create(['role' => 'moderator']);
        $room = $this->room($host, private: false, members: [$member]);

        foreach ([$member, $stranger, $moderator] as $user) {
            $this->as($user)->deleteJson("/api/rooms/{$room->id}")->assertForbidden();
        }
        $this->as($host)->deleteJson('/api/rooms/999999')->assertNotFound();
        $this->withHeaders(['Authorization' => ''])->deleteJson("/api/rooms/{$room->id}")->assertUnauthorized();

        $this->assertModelExists($room);
        Event::assertNotDispatched(RoomsChanged::class);
    }
}
