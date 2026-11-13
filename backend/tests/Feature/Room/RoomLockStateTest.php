<?php

namespace Tests\Feature\Room;

use App\Models\DiscussionRoom;
use App\Models\User;
use App\Room\DTOs\Requests\SendRoomMessageData;
use App\Room\Events\RoomMessageSent;
use App\Room\Events\RoomMessagesRead;
use App\Room\Events\RoomsChanged;
use App\Room\Exceptions\RoomLocked;
use App\Room\InterfaceServices\RoomServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Social\SocialTestCase;

/** `is_locked` is a real boolean in every room payload, and a post is refused against the stored state, not a stale copy. */
class RoomLockStateTest extends SocialTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([RoomMessageSent::class, RoomMessagesRead::class, RoomsChanged::class, ...self::NOTIFICATION_EVENTS]);
    }

    public function test_every_room_payload_has_a_boolean_is_locked(): void
    {
        [$lina, $omar, $sam] = User::factory()->count(3)->create()->all();

        $created = $this->as($lina)->postJson('/api/rooms', ['name' => 'Thriller Thursday', 'tmdb_id' => 550, 'is_private' => true])
            ->assertCreated()
            ->assertJsonPath('room.is_locked', false);
        $this->assertFalse($created->json('room.is_locked'));
        $id = $created->json('room.id');

        $this->as($lina)->getJson('/api/rooms')->assertJsonPath('rooms.0.is_locked', false);
        $this->as($lina)->postJson("/api/rooms/{$id}/invites", ['invitees' => [$omar->username]])->assertOk()->assertJsonPath('room.is_locked', false);
        $this->as($omar)->postJson("/api/rooms/{$id}/join")->assertOk()->assertJsonPath('room.is_locked', false);

        DiscussionRoom::whereKey($id)->update(['is_locked' => true]);

        $this->as($lina)->getJson('/api/rooms')->assertJsonPath('rooms.0.is_locked', true);
        $this->as($lina)->postJson("/api/rooms/{$id}/invites", ['invitees' => [$sam->username]])->assertOk()->assertJsonPath('room.is_locked', true);
        $this->as($sam)->postJson("/api/rooms/{$id}/join")->assertOk()->assertJsonPath('room.is_locked', true);
        $this->as($sam)->postJson("/api/rooms/{$id}/messages", ['content' => 'Hi'])->assertForbidden()->assertJsonPath('code', 'room_locked');
    }

    public function test_a_post_is_checked_against_the_stored_state_not_the_copy_the_request_loaded(): void
    {
        $lina = User::factory()->create();
        $room = DiscussionRoom::factory()->create(['created_by' => $lina->id]);
        DB::table('room_members')->insert(['room_id' => $room->id, 'user_id' => $lina->id]);
        $stale = DiscussionRoom::findOrFail($room->id);
        $this->assertFalse($stale->is_locked);

        DB::table('discussion_rooms')->where('id', $room->id)->update(['is_locked' => true]);

        try {
            app(RoomServiceInterface::class)->send($stale, new SendRoomMessageData('Too late'), $lina);
            $this->fail('The post should have been refused.');
        } catch (RoomLocked) {
            $this->assertDatabaseCount('messages', 0);
            Event::assertNotDispatched(RoomMessageSent::class);
        }
    }
}
