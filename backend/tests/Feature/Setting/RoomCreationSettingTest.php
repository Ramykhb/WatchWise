<?php

namespace Tests\Feature\Setting;

use App\Models\DiscussionRoom;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Social\SocialTestCase;

/** `rooms_creation_enabled=false` refuses new rooms while the existing ones keep working. */
class RoomCreationSettingTest extends SocialTestCase
{
    use ChangesSettings;

    /**
     * @return array<string, mixed>
     */
    private function newRoom(): array
    {
        return ['name' => 'Oscars 2026 picks', 'tmdb_id' => 1007];
    }

    public function test_members_can_create_rooms_by_default(): void
    {
        $this->as(User::factory()->create())->postJson('/api/rooms', $this->newRoom())->assertCreated();
    }

    public function test_a_disabled_room_creation_is_refused_with_a_clear_403_and_creates_nothing(): void
    {
        $this->changeSettings(['rooms_creation_enabled' => false]);

        foreach ([$this->newRoom(), [...$this->newRoom(), 'is_private' => true]] as $body) {
            $this->as(User::factory()->create())->postJson('/api/rooms', $body)
                ->assertForbidden()
                ->assertExactJson(['message' => 'Creating discussion rooms is turned off right now.', 'code' => 'rooms_creation_disabled']);
        }

        $this->assertSame(0, DiscussionRoom::count());
    }

    public function test_existing_rooms_keep_working_while_creation_is_disabled(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $room = DiscussionRoom::create(['tmdb_id' => 1003, 'name' => 'Thriller Thursday', 'created_by' => $host->id]);
        DB::table('room_members')->insert(['room_id' => $room->id, 'user_id' => $host->id]);
        $this->changeSettings(['rooms_creation_enabled' => false]);

        $this->as($guest)->getJson('/api/rooms')->assertOk()->assertJsonCount(1, 'rooms');
        $this->as($guest)->postJson("/api/rooms/{$room->id}/join")->assertOk();
        $this->as($guest)->postJson("/api/rooms/{$room->id}/messages", ['content' => 'Still here.'])->assertCreated();
        $this->as($host)->deleteJson("/api/rooms/{$room->id}")->assertOk();
    }

    public function test_enabling_it_again_lets_members_create_rooms(): void
    {
        $this->changeSettings(['rooms_creation_enabled' => false]);
        $member = User::factory()->create();
        $this->as($member)->postJson('/api/rooms', $this->newRoom())->assertForbidden();

        $this->changeSettings(['rooms_creation_enabled' => true]);

        $this->as($member)->postJson('/api/rooms', $this->newRoom())->assertCreated();
    }
}
