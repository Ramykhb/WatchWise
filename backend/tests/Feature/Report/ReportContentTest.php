<?php

namespace Tests\Feature\Report;

use App\Models\DiscussionRoom;
use App\Models\Message;
use App\Models\MovieList;
use App\Models\Report;
use App\Models\User;
use App\Report\Support\ReportTargetType;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Social\SocialTestCase;

/** Members report messages, lists, rooms and profiles: who may, what is stored and what a repeat does. */
class ReportContentTest extends SocialTestCase
{
    private function room(User $creator, bool $private = false): DiscussionRoom
    {
        $room = DiscussionRoom::create(['tmdb_id' => 550, 'name' => 'Fight Club night', 'created_by' => $creator->id, 'is_private' => $private]);
        DB::table('room_members')->insert(['room_id' => $room->id, 'user_id' => $creator->id]);

        return $room;
    }

    private function message(DiscussionRoom $room, User $author, string $content = 'Buy followers here'): Message
    {
        return Message::create(['room_id' => $room->id, 'user_id' => $author->id, 'content' => $content]);
    }

    private function list(User $owner, bool $public = true): MovieList
    {
        return MovieList::create(['user_id' => $owner->id, 'title' => 'Best heists', 'is_public' => $public]);
    }

    /**
     * Every kind of content with a way to file a report about something made by `$owner`.
     *
     * @return array<string, array{0: string, 1: ReportTargetType, 2: int}>
     */
    private function targets(User $owner): array
    {
        $room = $this->room($owner);
        $message = $this->message($room, $owner);
        $list = $this->list($owner);

        return [
            'message' => ["/api/rooms/{$room->id}/messages/{$message->id}/report", ReportTargetType::Message, $message->id],
            'list' => ["/api/lists/{$list->id}/report", ReportTargetType::MovieList, $list->id],
            'room' => ["/api/rooms/{$room->id}/report", ReportTargetType::Room, $room->id],
            'profile' => ["/api/users/{$owner->username}/report", ReportTargetType::Profile, $owner->id],
        ];
    }

    public function test_a_member_can_report_each_kind_of_content(): void
    {
        $owner = User::factory()->create();
        $reporter = User::factory()->create();

        foreach ($this->targets($owner) as $kind => [$url, $type, $id]) {
            $reason = $type->reasons()[1];

            $this->as($reporter)->postJson($url, ['reason' => $reason, 'details' => 'Seen it twice.'])
                ->assertCreated()
                ->assertJsonPath('report.target_type', $type->value)
                ->assertJsonPath('report.target_id', $id)
                ->assertJsonPath('report.reason', $reason)
                ->assertJsonPath('report.details', 'Seen it twice.')
                ->assertJsonPath('report.status', 'open')
                ->assertJsonStructure(['message', 'report' => ['id', 'created_at']]);

            $this->assertDatabaseHas('reports', [
                'reporter_id' => $reporter->id, 'target_type' => $type->value, 'target_id' => $id,
                'reason' => $reason, 'details' => 'Seen it twice.', 'status' => 'open', 'resolved_at' => null,
            ]);
        }

        $this->assertSame(4, Report::count());
    }

    public function test_reporting_the_same_content_again_returns_the_first_report_and_adds_nothing(): void
    {
        $owner = User::factory()->create();
        $reporter = User::factory()->create();

        foreach ($this->targets($owner) as [$url, $type, $id]) {
            $first = $this->as($reporter)->postJson($url, ['reason' => $type->reasons()[0], 'details' => 'First'])->assertCreated()->json('report.id');

            $this->as($reporter)->postJson($url, ['reason' => $type->reasons()[1], 'details' => 'Second'])
                ->assertOk()
                ->assertJsonPath('report.id', $first)
                ->assertJsonPath('report.reason', $type->reasons()[0])
                ->assertJsonPath('report.details', 'First');
        }

        $this->assertSame(4, Report::count());
    }

    public function test_two_members_can_report_the_same_content(): void
    {
        $owner = User::factory()->create();
        [$url] = $this->targets($owner)['list'];

        $this->as(User::factory()->create())->postJson($url, ['reason' => 'spam'])->assertCreated();
        $this->as(User::factory()->create())->postJson($url, ['reason' => 'other'])->assertCreated();

        $this->assertSame(2, Report::count());
    }

    public function test_guests_cannot_report(): void
    {
        foreach ($this->targets(User::factory()->create()) as [$url]) {
            $this->postJson($url, ['reason' => 'spam'])->assertUnauthorized();
        }

        $this->assertSame(0, Report::count());
    }

    public function test_you_cannot_report_your_own_content_or_profile(): void
    {
        $owner = User::factory()->create();

        foreach ($this->targets($owner) as [$url, $type]) {
            $this->as($owner)->postJson($url, ['reason' => $type->reasons()[0]])->assertForbidden();
        }

        $this->assertSame(0, Report::count());
    }

    public function test_content_the_reporter_cannot_see_cannot_be_reported(): void
    {
        $owner = User::factory()->create();
        $reporter = User::factory()->create();
        $privateRoom = $this->room($owner, private: true);
        $message = $this->message($privateRoom, $owner);
        $privateList = $this->list($owner, public: false);

        $this->as($reporter)->postJson("/api/rooms/{$privateRoom->id}/report", ['reason' => 'spam'])->assertForbidden();
        $this->as($reporter)->postJson("/api/rooms/{$privateRoom->id}/messages/{$message->id}/report", ['reason' => 'spam'])->assertForbidden();
        $this->as($reporter)->postJson("/api/lists/{$privateList->id}/report", ['reason' => 'spam'])->assertForbidden();

        $this->assertSame(0, Report::count());
    }

    public function test_any_member_can_report_a_private_or_followers_only_profile(): void
    {
        $reporter = User::factory()->create();

        foreach (['private', 'followers'] as $visibility) {
            $owner = User::factory()->create(['profile_visibility' => $visibility]);

            $this->as($reporter)->postJson("/api/users/{$owner->username}/report", ['reason' => 'harassment'])
                ->assertCreated()
                ->assertJsonPath('report.target_id', $owner->id)
                ->assertJsonMissingPath('report.user');
        }

        $this->assertSame(2, Report::count());
    }

    public function test_a_deactivated_members_profile_cannot_be_reported(): void
    {
        $owner = User::factory()->create(['is_active' => false]);

        $this->as(User::factory()->create())->postJson("/api/users/{$owner->username}/report", ['reason' => 'spam'])->assertForbidden();

        $this->assertSame(0, Report::count());
    }

    public function test_an_invited_member_can_report_a_private_room(): void
    {
        $owner = User::factory()->create();
        $invited = User::factory()->create();
        $room = $this->room($owner, private: true);
        DB::table('room_invites')->insert(['room_id' => $room->id, 'user_id' => $invited->id, 'invited_by' => $owner->id]);

        $this->as($invited)->postJson("/api/rooms/{$room->id}/report", ['reason' => 'spam'])->assertCreated();
    }

    public function test_unknown_content_and_a_message_from_another_room_answer_404(): void
    {
        $owner = User::factory()->create();
        $reporter = User::factory()->create();
        $room = $this->room($owner);
        $otherRoom = $this->room($owner);
        $message = $this->message($otherRoom, $owner);

        $this->as($reporter)->postJson("/api/rooms/{$room->id}/messages/{$message->id}/report", ['reason' => 'spam'])->assertNotFound();
        $this->as($reporter)->postJson("/api/rooms/{$room->id}/messages/9999/report", ['reason' => 'spam'])->assertNotFound();
        $this->as($reporter)->postJson('/api/rooms/9999/report', ['reason' => 'spam'])->assertNotFound();
        $this->as($reporter)->postJson('/api/lists/9999/report', ['reason' => 'spam'])->assertNotFound();
        $this->as($reporter)->postJson('/api/users/nobody-here/report', ['reason' => 'spam'])->assertNotFound();
    }

    public function test_each_kind_accepts_only_its_own_reasons(): void
    {
        $owner = User::factory()->create();

        foreach ($this->targets($owner) as $kind => [$url, $type]) {
            $reporter = User::factory()->create();

            foreach ($type->reasons() as $reason) {
                $this->as($reporter)->postJson($url, ['reason' => $reason])->assertSuccessful();
                Report::query()->delete();
            }

            $foreign = $kind === 'message' ? 'inappropriate' : 'hate';
            $this->as($reporter)->postJson($url, ['reason' => $foreign])->assertUnprocessable()->assertJsonValidationErrors('reason');
            $this->as($reporter)->postJson($url, ['reason' => 'Spam'])->assertUnprocessable()->assertJsonValidationErrors('reason');
            $this->as($reporter)->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('reason');
        }

        $this->assertSame(0, Report::count());
    }

    public function test_details_may_be_up_to_500_characters(): void
    {
        $owner = User::factory()->create();
        $reporter = User::factory()->create();
        [$url] = $this->targets($owner)['list'];

        $this->as($reporter)->postJson($url, ['reason' => 'spam', 'details' => str_repeat('a', 501)])->assertUnprocessable()->assertJsonValidationErrors('details');
        $this->as($reporter)->postJson($url, ['reason' => 'spam', 'details' => str_repeat('a', 500)])->assertCreated();
    }

    public function test_details_are_optional_and_stored_as_null(): void
    {
        $owner = User::factory()->create();
        [$url] = $this->targets($owner)['profile'];

        $this->as(User::factory()->create())->postJson($url, ['reason' => 'spam'])->assertCreated()->assertJsonPath('report.details', null);
    }

    public function test_a_member_may_file_twenty_reports_an_hour(): void
    {
        $owner = User::factory()->create();
        $reporter = User::factory()->create();
        [$url] = $this->targets($owner)['list'];

        for ($i = 0; $i < 20; $i++) {
            $this->as($reporter)->postJson($url, ['reason' => 'spam'])->assertSuccessful();
        }

        $this->as($reporter)->postJson($url, ['reason' => 'spam'])->assertStatus(429);
        $this->as(User::factory()->create())->postJson($url, ['reason' => 'spam'])->assertCreated();
    }

    public function test_reporting_again_after_staff_closed_the_report_reopens_it_as_new(): void
    {
        $owner = User::factory()->create();
        $reporter = User::factory()->create();
        $staff = User::factory()->admin()->create();

        foreach ($this->targets($owner) as [$url, $type]) {
            $id = $this->as($reporter)->postJson($url, ['reason' => $type->reasons()[0], 'details' => 'First'])->assertCreated()->json('report.id');
            Report::query()->whereKey($id)->update([
                'status' => 'dismissed', 'resolved_at' => now()->subDay(), 'resolved_by' => $staff->id,
                'resolution_note' => 'Looked fine', 'created_at' => now()->subWeek(),
            ]);

            $this->as($reporter)->postJson($url, ['reason' => $type->reasons()[1], 'details' => 'Again'])
                ->assertOk()
                ->assertJsonPath('report.id', $id)
                ->assertJsonPath('report.status', 'open')
                ->assertJsonPath('report.reason', $type->reasons()[1])
                ->assertJsonPath('report.details', 'Again');

            $report = Report::query()->findOrFail($id);
            $this->assertSame('open', $report->status->value);
            $this->assertNull($report->resolved_at);
            $this->assertNull($report->resolved_by);
            $this->assertNull($report->resolution_note);
            $this->assertTrue($report->created_at->isToday());
        }

        $this->assertSame(4, Report::count());
    }

    public function test_a_reopened_report_sorts_as_the_newest_in_the_queue(): void
    {
        $owner = User::factory()->create();
        $reporter = User::factory()->create();
        [$url] = $this->targets($owner)['list'];
        $id = $this->as($reporter)->postJson($url, ['reason' => 'spam'])->json('report.id');
        Report::query()->whereKey($id)->update(['status' => 'resolved', 'resolved_at' => now(), 'created_at' => now()->subWeek()]);
        $newer = Report::factory()->create(['created_at' => now()->subDay()]);

        $this->as($reporter)->postJson($url, ['reason' => 'other'])->assertOk();

        $this->assertSame([$id, $newer->id], $this->as(User::factory()->admin()->create())->getJson('/api/admin/reports')->json('data.*.id'));
    }

    public function test_a_new_report_keeps_a_snapshot_of_what_was_reported(): void
    {
        $owner = User::factory()->create(['username' => 'maya']);
        $room = $this->room($owner);
        $message = $this->message($room, $owner, str_repeat('é', 300));
        $list = $this->list($owner);
        $reporter = User::factory()->create();

        $this->as($reporter)->postJson("/api/rooms/{$room->id}/messages/{$message->id}/report", ['reason' => 'spam'])->assertCreated();
        $this->as($reporter)->postJson("/api/lists/{$list->id}/report", ['reason' => 'spam'])->assertCreated();
        $this->as($reporter)->postJson('/api/users/maya/report', ['reason' => 'spam'])->assertCreated();

        $byType = Report::query()->get()->keyBy(fn (Report $report): string => $report->target_type->value);
        $this->assertSame('Message by maya in Fight Club night', $byType['message']->target_label);
        $this->assertSame(str_repeat('é', 200), $byType['message']->target_excerpt);
        $this->assertSame('List by maya', $byType['list']->target_label);
        $this->assertSame('Best heists', $byType['list']->target_excerpt);
        $this->assertSame('Profile of maya', $byType['profile']->target_label);
        $this->assertSame('maya', $byType['profile']->target_excerpt);
    }

    public function test_a_long_label_is_cut_to_120_characters(): void
    {
        $owner = User::factory()->create();
        $room = DiscussionRoom::create(['tmdb_id' => 550, 'name' => str_repeat('r', 200), 'created_by' => $owner->id]);
        $message = $this->message($room, $owner);

        $this->as(User::factory()->create())->postJson("/api/rooms/{$room->id}/messages/{$message->id}/report", ['reason' => 'spam'])->assertCreated();

        $this->assertSame(120, mb_strlen(Report::query()->firstOrFail()->target_label));
    }

    public function test_a_repeat_of_an_open_report_keeps_its_snapshot_and_a_reopened_one_refreshes_it(): void
    {
        $owner = User::factory()->create();
        $reporter = User::factory()->create();
        $list = $this->list($owner);
        $url = "/api/lists/{$list->id}/report";

        $this->as($reporter)->postJson($url, ['reason' => 'spam'])->assertCreated();
        $list->update(['title' => 'Renamed once']);
        $this->as($reporter)->postJson($url, ['reason' => 'other'])->assertOk();

        $this->assertSame('Best heists', Report::query()->firstOrFail()->target_excerpt);

        Report::query()->update(['status' => 'resolved', 'resolved_at' => now()]);
        $list->update(['title' => 'Renamed twice']);
        $this->as($reporter)->postJson($url, ['reason' => 'other'])->assertOk();

        $this->assertSame('Renamed twice', Report::query()->firstOrFail()->target_excerpt);
    }
}
