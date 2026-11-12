<?php

namespace Tests\Feature\Room;

use App\Models\DiscussionRoom;
use App\Models\LocalMovie;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesMovieCatalogue;
use Tests\Feature\Social\SocialTestCase;

class HiddenMoviesRoomTest extends SocialTestCase
{
    use UsesMovieCatalogue;

    private const HIDDEN = 101;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useRealCatalogue();
        $this->hide(self::HIDDEN);
    }

    private function room(User $creator, int $tmdbId = self::HIDDEN, string $mediaType = 'movie', bool $private = false): DiscussionRoom
    {
        $room = DiscussionRoom::create(['tmdb_id' => $tmdbId, 'media_type' => $mediaType, 'name' => 'Room '.$tmdbId, 'created_by' => $creator->id, 'is_private' => $private]);
        DB::table('room_members')->insert(['room_id' => $room->id, 'user_id' => $creator->id]);

        return $room;
    }

    /**
     * @return list<int>
     */
    private function listedFor(User $viewer): array
    {
        return collect($this->as($viewer)->getJson('/api/rooms')->assertOk()->json('rooms'))->pluck('id')->all();
    }

    public function test_a_public_room_about_a_hidden_movie_is_not_listed_for_a_stranger(): void
    {
        $room = $this->room(User::factory()->create());
        $visible = $this->room(User::factory()->create(), 7);

        $this->assertSame([$visible->id], $this->listedFor(User::factory()->create()));
        $this->assertDatabaseHas('discussion_rooms', ['id' => $room->id]);
    }

    public function test_its_creator_and_members_still_see_the_room(): void
    {
        $creator = User::factory()->create();
        $member = User::factory()->create();
        $room = $this->room($creator);
        DB::table('room_members')->insert(['room_id' => $room->id, 'user_id' => $member->id]);

        $this->assertSame([$room->id], $this->listedFor($creator));
        $this->assertSame([$room->id], $this->listedFor($member));
    }

    public function test_an_invited_member_still_sees_the_room(): void
    {
        $creator = User::factory()->create();
        $invitee = User::factory()->create();
        $room = $this->room($creator, private: true);
        DB::table('room_invites')->insert(['room_id' => $room->id, 'user_id' => $invitee->id, 'invited_by' => $creator->id]);

        $this->assertSame([$room->id], $this->listedFor($invitee));
    }

    public function test_a_tv_room_with_the_same_id_is_unaffected(): void
    {
        $room = $this->room(User::factory()->create(), self::HIDDEN, 'tv');

        $this->assertSame([$room->id], $this->listedFor(User::factory()->create()));
    }

    public function test_showing_the_movie_again_lists_the_room_again(): void
    {
        $room = $this->room(User::factory()->create());
        $stranger = User::factory()->create();

        $this->assertSame([], $this->listedFor($stranger));

        LocalMovie::where('tmdb_id', self::HIDDEN)->update(['is_hidden' => false]);

        $this->assertSame([$room->id], $this->listedFor($stranger));
    }
}
