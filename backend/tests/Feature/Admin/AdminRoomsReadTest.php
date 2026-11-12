<?php

namespace Tests\Feature\Admin;

use App\Models\DiscussionRoom;
use App\Models\LocalMovie;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Report;
use App\Models\User;
use App\Report\Support\ReportTargetType;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/** Staff read discussion rooms and their messages; a private room is only read to judge a report. */
class AdminRoomsReadTest extends AdminTestCase
{
    private function moderator(): User
    {
        return User::factory()->create(['role' => 'moderator']);
    }

    /**
     * @param  list<User>  $members
     */
    private function room(array $attributes = [], array $members = []): DiscussionRoom
    {
        $room = DiscussionRoom::factory()->create($attributes);

        foreach ($members as $member) {
            DB::table('room_members')->insert(['room_id' => $room->id, 'user_id' => $member->id]);
        }

        return $room;
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function routes(): array
    {
        return [
            'list' => ['getJson', '/api/admin/rooms', true],
            'show' => ['getJson', '/api/admin/rooms/ROOM', true],
            'messages' => ['getJson', '/api/admin/rooms/ROOM/messages', true],
            'lock' => ['patchJson', '/api/admin/rooms/ROOM', true],
            'delete message' => ['deleteJson', '/api/admin/rooms/ROOM/messages/MESSAGE', true],
            'delete room' => ['deleteJson', '/api/admin/rooms/ROOM', false],
        ];
    }

    #[DataProvider('routes')]
    public function test_the_access_matrix_of_every_route(string $method, string $uri, bool $moderatorsAllowed): void
    {
        $room = $this->room();
        $message = Message::factory()->for($room, 'room')->create();
        $uri = str_replace(['ROOM', 'MESSAGE'], [(string) $room->id, (string) $message->id], $uri);

        $this->$method($uri)->assertUnauthorized();
        $this->as(User::factory()->create())->$method($uri)->assertForbidden();

        $moderator = $this->as($this->moderator())->$method($uri, $method === 'patchJson' ? ['is_locked' => true] : []);
        $moderatorsAllowed ? $moderator->assertSuccessful()->assertJsonMissingPath('errors') : $moderator->assertForbidden();
        $this->assertModelExists($room);
    }

    public function test_an_admin_may_use_every_route(): void
    {
        $admin = $this->admin();
        $room = $this->room();
        $message = Message::factory()->for($room, 'room')->create();

        $this->as($admin)->getJson('/api/admin/rooms')->assertOk();
        $this->as($admin)->getJson("/api/admin/rooms/{$room->id}")->assertOk();
        $this->as($admin)->getJson("/api/admin/rooms/{$room->id}/messages")->assertOk();
        $this->as($admin)->patchJson("/api/admin/rooms/{$room->id}", ['is_locked' => true])->assertOk();
        $this->as($admin)->deleteJson("/api/admin/rooms/{$room->id}/messages/{$message->id}")->assertOk();
        $this->as($admin)->deleteJson("/api/admin/rooms/{$room->id}")->assertOk();
    }

    public function test_the_list_gives_each_room_its_row_and_the_page_its_summary(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 550, 'title' => 'Fight Club']);
        $creator = User::factory()->create(['name' => 'Lina Host', 'username' => 'linahost']);
        $member = User::factory()->create();
        $room = $this->room(['name' => 'Twist talk', 'tmdb_id' => 550, 'created_by' => $creator->id], [$creator, $member]);
        $message = Message::factory()->for($room, 'room')->create(['created_at' => now()->subMinutes(5)]);
        Report::factory()->forTarget(ReportTargetType::Room, $room)->create();
        Report::factory()->forTarget(ReportTargetType::Message, $message)->create();
        Report::factory()->forTarget(ReportTargetType::Message, $message)->create();
        $this->room(['is_private' => true, 'is_locked' => true]);
        $this->room(['media_type' => 'tv', 'tmdb_id' => 550]);

        $response = $this->as($this->moderator())->getJson('/api/admin/rooms')->assertOk()
            ->assertJsonPath('summary', ['total' => 3, 'public' => 2, 'private' => 1, 'locked' => 1, 'reported' => 1])
            ->assertJsonPath('meta.total', 3);

        $row = collect($response->json('data'))->firstWhere('id', $room->id);
        $this->assertSame([
            'id' => $room->id,
            'name' => 'Twist talk',
            'tmdb_id' => 550,
            'media_type' => 'movie',
            'film' => 'Fight Club',
            'is_private' => false,
            'is_locked' => false,
            'spoilers' => false,
            'creator' => ['id' => $creator->id, 'name' => 'Lina Host', 'username' => 'linahost'],
            'members_count' => 2,
            'messages_count' => 1,
            'last_activity_at' => $message->created_at->toJSON(),
            'open_reports_count' => 1,
            'open_message_reports_count' => 2,
            'created_at' => $room->created_at->toJSON(),
        ], $row);
        $tv = collect($response->json('data'))->first(fn (array $row): bool => $row['media_type'] === 'tv');
        $this->assertNull($tv['film']);
    }

    public function test_the_list_filters(): void
    {
        $lina = User::factory()->create(['name' => 'Lina Host', 'username' => 'linahost']);
        LocalMovie::factory()->create(['tmdb_id' => 603, 'title' => 'The Matrix']);
        $matrix = $this->room(['name' => 'Red pill', 'tmdb_id' => 603]);
        $private = $this->room(['name' => 'Secret_100%', 'is_private' => true, 'created_by' => $lina->id]);
        $locked = $this->room(['name' => 'Quiet', 'is_locked' => true]);
        $reported = $this->room(['name' => 'Loud']);
        $message = Message::factory()->for($reported, 'room')->create();
        Report::factory()->forTarget(ReportTargetType::Message, $message)->create();
        Report::factory()->forTarget(ReportTargetType::Room, $locked)->resolved()->create();
        $moderator = $this->as($this->moderator());

        $ids = fn (string $query): array => collect($moderator->getJson("/api/admin/rooms?{$query}")->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame([$matrix->id], $ids('q=matrix'));
        $this->assertSame([$matrix->id], $ids('q=red+pill'));
        $this->assertSame([$private->id], $ids('q=linahost'));
        $this->assertSame([$private->id], $ids('q=Lina+Host'));
        $this->assertSame([$private->id], $ids('q=_100%25'));
        $this->assertSame([], $ids('q=zzzz'));
        $this->assertSame([$private->id], $ids('visibility=private'));
        $this->assertCount(3, $ids('visibility=public'));
        $this->assertSame([$private->id], $ids("creator_id={$lina->id}"));
        $this->assertSame([$matrix->id], $ids('tmdb_id=603'));
        $this->assertSame([$locked->id], $ids('locked=1'));
        $this->assertCount(3, $ids('locked=0'));
        $this->assertSame([$reported->id], $ids('reported=1'));
        $this->assertCount(3, $ids('reported=0'));
        $moderator->getJson('/api/admin/rooms?visibility=hidden')->assertUnprocessable();
        $moderator->getJson('/api/admin/rooms?per_page=51')->assertUnprocessable();
    }

    public function test_rooms_come_newest_activity_first_and_paginate(): void
    {
        $quiet = $this->room(['name' => 'Quiet', 'created_at' => now()->subDays(3)]);
        $busy = $this->room(['name' => 'Busy', 'created_at' => now()->subDays(5)]);
        Message::factory()->for($busy, 'room')->create(['created_at' => now()->subHour()]);
        $fresh = $this->room(['name' => 'Fresh', 'created_at' => now()->subMinute()]);
        $tieA = $this->room(['created_at' => now()->subDays(9)]);
        $tieB = $this->room(['created_at' => now()->subDays(9)]);
        $moderator = $this->as($this->moderator());

        $this->assertSame(
            [$fresh->id, $busy->id, $quiet->id, $tieB->id, $tieA->id],
            collect($moderator->getJson('/api/admin/rooms')->json('data'))->pluck('id')->all(),
        );

        $page = $moderator->getJson('/api/admin/rooms?per_page=2&page=2')->assertOk()
            ->assertJsonPath('meta', ['current_page' => 2, 'last_page' => 3, 'per_page' => 2, 'total' => 5]);
        $this->assertSame([$quiet->id, $tieB->id], collect($page->json('data'))->pluck('id')->all());
    }

    public function test_the_list_uses_a_constant_number_of_queries(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->as($this->moderator())->getJson('/api/admin/rooms?per_page=50')->assertOk();

            return count(DB::getQueryLog());
        };

        $this->makeRooms(2);
        $few = $count();
        $this->makeRooms(8);

        $this->assertSame($few, $count());
    }

    private function makeRooms(int $number): void
    {
        for ($i = 0; $i < $number; $i++) {
            $room = $this->room(['tmdb_id' => 1000 + $i], [User::factory()->create()]);
            $message = Message::factory()->for($room, 'room')->create();
            Report::factory()->forTarget(ReportTargetType::Message, $message)->create();
            Report::factory()->forTarget(ReportTargetType::Room, $room)->create();
        }
    }

    public function test_a_hidden_film_does_not_hide_a_room_from_staff(): void
    {
        LocalMovie::factory()->hidden()->create(['tmdb_id' => 550, 'title' => 'Fight Club']);
        $room = $this->room(['tmdb_id' => 550]);

        $this->as($this->moderator())->getJson('/api/admin/rooms')->assertJsonPath('data.0.id', $room->id)->assertJsonPath('data.0.film', 'Fight Club');
        $this->as($this->moderator())->getJson("/api/admin/rooms/{$room->id}")->assertOk();
    }

    public function test_the_detail_has_the_members_and_the_open_reports_on_the_room(): void
    {
        [$lina, $omar, $reporter] = User::factory()->count(3)->create()->all();
        $room = $this->room(['created_by' => $lina->id, 'is_private' => true], [$lina, $omar]);
        $message = Message::factory()->for($room, 'room')->create();
        $open = Report::factory()->forTarget(ReportTargetType::Room, $room, 'harassment')->create(['reporter_id' => $reporter->id, 'details' => 'Names people']);
        Report::factory()->forTarget(ReportTargetType::Room, $room)->resolved()->create();
        Report::factory()->forTarget(ReportTargetType::Message, $message)->create();

        $response = $this->as($this->moderator())->getJson("/api/admin/rooms/{$room->id}")->assertOk()
            ->assertJsonPath('room.id', $room->id)
            ->assertJsonPath('room.is_private', true)
            ->assertJsonPath('room.open_reports_count', 1)
            ->assertJsonPath('room.open_message_reports_count', 1)
            ->assertJsonCount(2, 'room.members')
            ->assertJsonCount(1, 'room.open_reports')
            ->assertJsonPath('room.members.0.id', $lina->id)
            ->assertJsonPath('room.members.1.username', $omar->username)
            ->assertJsonPath('room.open_reports.0.id', $open->id)
            ->assertJsonPath('room.open_reports.0.reporter', ['id' => $reporter->id, 'name' => $reporter->name, 'username' => $reporter->username])
            ->assertJsonPath('room.open_reports.0.reason', 'harassment')
            ->assertJsonPath('room.open_reports.0.details', 'Names people');
        $this->assertNotNull($response->json('room.members.0.joined_at'));
        $this->assertNotNull($response->json('room.open_reports.0.created_at'));
        $this->as($this->moderator())->getJson('/api/admin/rooms/999999')->assertNotFound();
    }

    public function test_the_members_of_a_room_are_capped_at_200(): void
    {
        $room = $this->room();
        $now = now();
        $users = User::factory()->count(205)->create();
        DB::table('room_members')->insert($users->map(fn (User $user): array => ['room_id' => $room->id, 'user_id' => $user->id, 'created_at' => $now])->all());

        $this->as($this->moderator())->getJson("/api/admin/rooms/{$room->id}")
            ->assertJsonPath('room.members_count', 205)
            ->assertJsonCount(200, 'room.members');
    }

    public function test_the_messages_of_a_public_room_are_read_newest_first_with_their_report_counts(): void
    {
        $author = User::factory()->create(['name' => 'Omar', 'username' => 'omar']);
        $room = $this->room();
        $old = Message::factory()->for($room, 'room')->create(['user_id' => $author->id, 'content' => 'First', 'created_at' => now()->subHour()]);
        $new = Message::factory()->for($room, 'room')->create(['user_id' => $author->id, 'content' => 'Second', 'created_at' => now()]);
        Message::factory()->create();
        Report::factory()->forTarget(ReportTargetType::Message, $new)->count(2)->create();
        Report::factory()->forTarget(ReportTargetType::Message, $old)->resolved()->create();

        $response = $this->as($this->moderator())->getJson("/api/admin/rooms/{$room->id}/messages")->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->assertSame(
            [
                ['id' => $new->id, 'author' => ['id' => $author->id, 'name' => 'Omar', 'username' => 'omar'], 'body' => 'Second', 'created_at' => $new->created_at->toJSON(), 'open_reports_count' => 2],
                ['id' => $old->id, 'author' => ['id' => $author->id, 'name' => 'Omar', 'username' => 'omar'], 'body' => 'First', 'created_at' => $old->created_at->toJSON(), 'open_reports_count' => 0],
            ],
            $response->json('data'),
        );
        $this->as($this->moderator())->getJson("/api/admin/rooms/{$room->id}/messages?per_page=1&page=2")->assertJsonPath('data.0.id', $old->id);
        $this->as($this->moderator())->getJson("/api/admin/rooms/{$room->id}/messages?per_page=51")->assertUnprocessable();
    }

    public function test_a_private_room_is_readable_only_while_it_has_an_open_report(): void
    {
        $member = User::factory()->create();
        $room = $this->room(['is_private' => true], [$member]);
        $message = Message::factory()->for($room, 'room')->create(['user_id' => $member->id, 'content' => 'Private words']);
        $staff = $this->as($this->moderator());

        $staff->getJson("/api/admin/rooms/{$room->id}/messages")->assertForbidden()->assertJsonPath('code', 'room_not_reviewable');

        $roomReport = Report::factory()->forTarget(ReportTargetType::Room, $room)->create();
        $staff->getJson("/api/admin/rooms/{$room->id}/messages")->assertOk()->assertJsonPath('data.0.body', 'Private words');

        $roomReport->update(['status' => 'resolved', 'resolved_at' => now()]);
        $staff->getJson("/api/admin/rooms/{$room->id}/messages")->assertForbidden()->assertJsonPath('code', 'room_not_reviewable');

        Report::factory()->forTarget(ReportTargetType::Message, $message)->create();
        $staff->getJson("/api/admin/rooms/{$room->id}/messages")->assertOk();
    }

    public function test_reading_marks_nothing_read_and_leaves_the_receipts_alone(): void
    {
        [$author, $reader] = User::factory()->count(2)->create()->all();
        $room = $this->room([], [$author, $reader]);
        $message = Message::factory()->for($room, 'room')->create(['user_id' => $author->id]);
        $receipt = Notification::create(['user_id' => $reader->id, 'type' => Notification::ROOM_MESSAGE, 'data' => ['room_id' => $room->id, 'message_id' => $message->id]]);

        $this->as($this->moderator())->getJson("/api/admin/rooms/{$room->id}/messages")->assertOk();
        $this->as($this->admin())->getJson("/api/admin/rooms/{$room->id}")->assertOk();

        $this->assertNull($receipt->fresh()->read_at);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_the_search_never_matches_message_bodies(): void
    {
        $room = $this->room(['name' => 'Quiet room']);
        Message::factory()->for($room, 'room')->create(['content' => 'Needlephrase hidden in a message']);

        $this->as($this->moderator())->getJson('/api/admin/rooms?q=needlephrase')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_message_text_is_in_no_room_list_detail_lock_or_delete_response(): void
    {
        $text = 'Needlephrase never in a room payload';
        $room = $this->room(['is_private' => true]);
        $message = Message::factory()->for($room, 'room')->create(['content' => $text]);
        Report::factory()->forTarget(ReportTargetType::Room, $room)->create();
        $staff = $this->as($this->moderator());

        foreach ([
            $staff->getJson('/api/admin/rooms'),
            $staff->getJson("/api/admin/rooms/{$room->id}"),
            $staff->patchJson("/api/admin/rooms/{$room->id}", ['is_locked' => true]),
            $staff->deleteJson("/api/admin/rooms/{$room->id}/messages/{$message->id}"),
        ] as $response) {
            $response->assertOk();
            $this->assertStringNotContainsString($text, $response->getContent());
        }
    }

    public function test_a_reported_message_that_was_pruned_no_longer_unlocks_a_private_room(): void
    {
        $member = User::factory()->create();
        $room = $this->room(['is_private' => true], [$member]);
        $message = Message::factory()->for($room, 'room')->create([
            'user_id' => $member->id,
            'created_at' => now()->subDays(2),
            'read_tracking_started' => true,
        ]);
        Report::factory()->forTarget(ReportTargetType::Message, $message)->create();
        $staff = $this->as($this->moderator());
        $staff->getJson("/api/admin/rooms/{$room->id}/messages")->assertOk();

        $this->artisan('rooms:prune-messages')->assertSuccessful();

        $this->assertModelMissing($message);
        $this->assertSame(1, Report::open()->count());
        $staff->getJson("/api/admin/rooms/{$room->id}/messages")->assertForbidden()->assertJsonPath('code', 'room_not_reviewable');
        $staff->getJson('/api/admin/rooms?reported=1')->assertJsonPath('meta.total', 0);
    }
}
