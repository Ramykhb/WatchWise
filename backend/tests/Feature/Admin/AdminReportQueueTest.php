<?php

namespace Tests\Feature\Admin;

use App\Models\DiscussionRoom;
use App\Models\Message;
use App\Models\MovieList;
use App\Models\Report;
use App\Models\User;
use App\Report\Support\ReportTargetType;
use Illuminate\Support\Facades\DB;

/** The staff report queue: who may read it, what a row shows, how it filters, pages and orders. */
class AdminReportQueueTest extends AdminTestCase
{
    private function moderator(): User
    {
        return User::factory()->create(['role' => 'moderator']);
    }

    private function room(User $creator): DiscussionRoom
    {
        return DiscussionRoom::create(['tmdb_id' => 550, 'name' => 'Fight Club night', 'created_by' => $creator->id]);
    }

    /**
     * One report on each kind of content, all by their own reporters; returns them keyed by kind.
     *
     * @return array<string, Report>
     */
    private function onePerKind(?User $owner = null): array
    {
        $owner ??= User::factory()->create(['name' => 'Maya Owner', 'username' => 'maya']);
        $room = $this->room($owner);
        $message = Message::create(['room_id' => $room->id, 'user_id' => $owner->id, 'content' => 'Buy followers here']);
        $list = MovieList::create(['user_id' => $owner->id, 'title' => 'Best heists', 'is_public' => true]);
        $review = $this->review(['user_id' => $owner->id, 'is_approved' => true, 'content' => 'Great twist.']);

        return [
            'review' => Report::factory()->forReview($review)->create(),
            'message' => Report::factory()->forTarget(ReportTargetType::Message, $message)->create(),
            'list' => Report::factory()->forTarget(ReportTargetType::MovieList, $list)->create(),
            'room' => Report::factory()->forTarget(ReportTargetType::Room, $room)->create(),
            'profile' => Report::factory()->forTarget(ReportTargetType::Profile, $owner)->create(),
        ];
    }

    public function test_only_staff_can_read_the_queue(): void
    {
        $report = Report::factory()->create();

        $this->getJson('/api/admin/reports')->assertUnauthorized();
        $this->as(User::factory()->create())->getJson('/api/admin/reports')->assertForbidden();
        $this->as(User::factory()->create())->getJson("/api/admin/reports/{$report->id}")->assertForbidden();
        $this->as($this->moderator())->getJson('/api/admin/reports')->assertOk();
        $this->as($this->admin())->getJson('/api/admin/reports')->assertOk();
        $this->as($this->moderator())->getJson("/api/admin/reports/{$report->id}")->assertOk();
    }

    public function test_a_row_describes_the_report_its_reporter_and_its_target(): void
    {
        $reports = $this->onePerKind();
        $reports['message']->update(['reason' => 'harassment', 'details' => 'Keeps doing it']);

        $rows = collect($this->as($this->admin())->getJson('/api/admin/reports')->assertOk()->json('data'))->keyBy('target.type');

        $this->assertCount(5, $rows);
        $message = $rows['message'];
        $this->assertSame($reports['message']->id, $message['id']);
        $this->assertSame('open', $message['status']);
        $this->assertSame('harassment', $message['reason']);
        $this->assertSame('Keeps doing it', $message['details']);
        $this->assertSame($reports['message']->reporter_id, $message['reporter']['id']);
        $this->assertSame(['id', 'name', 'username'], array_keys($message['reporter']));
        $this->assertSame('Message by maya in Fight Club night', $message['target']['label']);
        $this->assertSame('Buy followers here', $message['target']['excerpt']);
        $this->assertSame('maya', $message['target']['owner']['username']);
        $this->assertFalse($message['target']['removed']);
        $this->assertNull($message['resolved_by']);
        $this->assertNull($message['resolution_note']);
        $this->assertNull($message['resolved_at']);
        $this->assertNotNull($message['created_at']);

        $this->assertSame('Best heists', $rows['list']['target']['excerpt']);
        $this->assertSame('Fight Club night', $rows['room']['target']['excerpt']);
        $this->assertSame('maya', $rows['profile']['target']['excerpt']);
        $this->assertSame('Great twist.', $rows['review']['target']['excerpt']);
        $this->assertSame('maya', $rows['review']['target']['owner']['username']);
    }

    public function test_the_excerpt_of_a_long_message_is_cut_to_200_characters(): void
    {
        $owner = User::factory()->create();
        $room = $this->room($owner);
        $message = Message::create(['room_id' => $room->id, 'user_id' => $owner->id, 'content' => str_repeat('é', 450)]);
        Report::factory()->forTarget(ReportTargetType::Message, $message)->create();

        $excerpt = $this->as($this->admin())->getJson('/api/admin/reports')->json('data.0.target.excerpt');

        $this->assertSame(200, mb_strlen($excerpt));
    }

    public function test_a_report_on_removed_content_stays_in_the_queue_marked_removed(): void
    {
        $reports = $this->onePerKind();
        $removedReview = $this->review(['is_approved' => true]);
        $removedReview->update(['content' => null, 'removed_at' => now()]);
        Report::factory()->forReview($removedReview)->create();
        DB::table('messages')->delete();
        DB::table('movie_lists')->delete();

        $rows = collect($this->as($this->admin())->getJson('/api/admin/reports')->assertOk()->json('data'));

        $gone = $rows->whereIn('id', [$reports['message']->id, $reports['list']->id]);
        $this->assertCount(2, $gone);

        foreach ($gone as $row) {
            $this->assertTrue($row['target']['removed']);
            $this->assertNull($row['target']['excerpt']);
            $this->assertNull($row['target']['owner']);
            $this->assertStringStartsWith('Removed ', $row['target']['label']);
        }

        $this->assertTrue($rows->firstWhere('target.id', $removedReview->id)['target']['removed']);
        $this->assertFalse($rows->firstWhere('id', $reports['room']->id)['target']['removed']);
        $this->assertFalse($rows->firstWhere('id', $reports['profile']->id)['target']['removed']);
    }

    public function test_a_report_on_deleted_content_shows_what_was_reported_when_it_was_filed(): void
    {
        $owner = User::factory()->create(['username' => 'maya']);
        $room = $this->room($owner);
        $message = Message::create(['room_id' => $room->id, 'user_id' => $owner->id, 'content' => 'Buy followers here']);
        $this->as(User::factory()->create())->postJson("/api/rooms/{$room->id}/messages/{$message->id}/report", ['reason' => 'spam'])->assertCreated();
        $message->delete();

        $this->as($this->admin())->getJson('/api/admin/reports')->assertOk()
            ->assertJsonPath('data.0.target.removed', true)
            ->assertJsonPath('data.0.target.label', 'Message by maya in Fight Club night')
            ->assertJsonPath('data.0.target.excerpt', 'Buy followers here')
            ->assertJsonPath('data.0.target.owner', null);
    }

    public function test_a_deleted_member_profile_is_marked_removed(): void
    {
        $member = User::factory()->create();
        Report::factory()->forTarget(ReportTargetType::Profile, $member)->create();
        $member->delete();

        $this->as($this->admin())->getJson('/api/admin/reports')->assertOk()
            ->assertJsonPath('data.0.target.removed', true)
            ->assertJsonPath('data.0.target.id', $member->id);
    }

    public function test_the_queue_shows_open_reports_by_default_and_filters_by_status(): void
    {
        $open = Report::factory()->create();
        $resolved = Report::factory()->resolved()->create();
        $dismissed = Report::factory()->dismissed()->create();
        $admin = $this->admin();

        $ids = fn (string $query): array => collect($this->as($admin)->getJson("/api/admin/reports{$query}")->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame([$open->id], $ids(''));
        $this->assertSame([$resolved->id], $ids('?status=resolved'));
        $this->assertSame([$dismissed->id], $ids('?status=dismissed'));
        $this->assertSame([$open->id, $resolved->id, $dismissed->id], $ids('?status=all'));
        $this->as($admin)->getJson('/api/admin/reports?status=bogus')->assertUnprocessable();
    }

    public function test_the_queue_filters_by_target_type_reason_and_reporter(): void
    {
        $reports = $this->onePerKind();
        $reports['review']->update(['reason' => 'Links']);
        $reports['list']->update(['reason' => 'harassment']);
        $reports['room']->update(['reason' => 'harassment']);
        $admin = $this->admin();

        $ids = fn (string $query): array => collect($this->as($admin)->getJson("/api/admin/reports?{$query}")->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame([$reports['message']->id], $ids('target_type=message'));
        $this->assertSame([$reports['review']->id], $ids('reason=Links'));
        $this->assertSame([$reports['list']->id, $reports['room']->id], $ids('reason=harassment'));
        $this->assertSame([$reports['room']->id], $ids('reason=harassment&target_type=room'));
        $this->assertSame([$reports['profile']->id], $ids('reporter_id='.$reports['profile']->reporter_id));
        $this->as($admin)->getJson('/api/admin/reports?target_type=comment')->assertUnprocessable();
        $this->as($admin)->getJson('/api/admin/reports?reason=boring')->assertUnprocessable();
        $this->as($admin)->getJson('/api/admin/reports?reporter_id=abc')->assertUnprocessable();
    }

    public function test_the_queue_is_newest_first_with_ids_breaking_ties_and_pages_carry_meta(): void
    {
        $tied = now()->subHour();
        $old = Report::factory()->create(['created_at' => now()->subDay()]);
        $firstTied = Report::factory()->create(['created_at' => $tied]);
        $secondTied = Report::factory()->create(['created_at' => $tied]);
        $newest = Report::factory()->create(['created_at' => now()]);
        $admin = $this->admin();

        $page = $this->as($admin)->getJson('/api/admin/reports?per_page=3')->assertOk();
        $this->assertSame([$newest->id, $secondTied->id, $firstTied->id], collect($page->json('data'))->pluck('id')->all());
        $page->assertJsonPath('meta.total', 4)->assertJsonPath('meta.last_page', 2)->assertJsonPath('meta.per_page', 3);

        $this->assertSame([$old->id], collect($this->as($admin)->getJson('/api/admin/reports?per_page=3&page=2')->json('data'))->pluck('id')->all());
        $this->as($admin)->getJson('/api/admin/reports?per_page=51')->assertUnprocessable();
    }

    public function test_the_summary_counts_reports_by_status_and_open_ones_by_kind(): void
    {
        $this->onePerKind();
        Report::factory()->forTarget(ReportTargetType::Profile, User::factory()->create())->create();
        Report::factory()->resolved()->create();
        Report::factory()->dismissed()->create();
        Report::factory()->dismissed()->create();

        $summary = $this->as($this->admin())->getJson('/api/admin/reports')->assertOk()->json('summary');

        $this->assertSame(['open' => 6, 'resolved' => 1, 'dismissed' => 2], $summary['by_status']);
        $this->assertSame(['review' => 1, 'message' => 1, 'list' => 1, 'room' => 1, 'profile' => 2], $summary['by_target_type']);
    }

    public function test_one_report_can_be_read_with_its_target(): void
    {
        $reports = $this->onePerKind();
        $report = $reports['list'];

        $this->as($this->moderator())->getJson("/api/admin/reports/{$report->id}")->assertOk()
            ->assertJsonPath('report.id', $report->id)
            ->assertJsonPath('report.target.type', 'list')
            ->assertJsonPath('report.target.excerpt', 'Best heists')
            ->assertJsonPath('report.reporter.id', $report->reporter_id);
        $this->as($this->moderator())->getJson('/api/admin/reports/9999')->assertNotFound();
    }

    public function test_the_number_of_queries_does_not_grow_with_the_reports(): void
    {
        $this->onePerKind();
        $admin = $this->admin();
        $this->as($admin)->getJson('/api/admin/reports')->assertOk();

        $count = fn (): int => $this->countQueries(fn () => $this->as($admin)->getJson('/api/admin/reports?per_page=50')->assertOk());

        $few = $count();

        for ($i = 0; $i < 4; $i++) {
            $this->onePerKind(User::factory()->create());
        }
        Report::factory()->count(6)->create(['target_id' => 9999]);

        $this->assertSame($few, $count());
        $this->assertCount(31, $this->as($admin)->getJson('/api/admin/reports?per_page=50')->json('data'));
    }

    /**
     * @param  callable(): mixed  $action
     */
    private function countQueries(callable $action): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $action();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    }
}
