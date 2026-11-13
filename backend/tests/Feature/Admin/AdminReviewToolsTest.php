<?php

namespace Tests\Feature\Admin;

use App\Admin\Support\AdminAction;
use App\Models\AdminAuditLog;
use App\Models\LocalMovie;
use App\Models\Notification;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/** The richer review tools of the moderation queue: the full view, the author and title filters, spoiler flags and bulk decisions. */
class AdminReviewToolsTest extends AdminTestCase
{
    private function moderator(): User
    {
        return User::factory()->create(['role' => 'moderator']);
    }

    /** @return list<AdminAuditLog> */
    private function entries(): array
    {
        return AdminAuditLog::query()->orderBy('id')->get()->all();
    }

    private function countStatsInvalidations(): \Closure
    {
        $writes = 0;
        Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$writes): void {
            if ($event->key === 'admin:stats:version') {
                $writes++;
            }
        });

        return function () use (&$writes): int {
            return $writes;
        };
    }

    public function test_the_detail_carries_the_full_review_its_reports_and_its_author(): void
    {
        $this->freezeTime();
        LocalMovie::factory()->create(['tmdb_id' => 550, 'title' => 'Fight Club']);
        $text = str_repeat('A very long opinion about the ending. ', 40);
        $review = $this->review(['content' => $text, 'is_approved' => true, 'has_spoilers' => true, 'moderation_labels' => ['spoiler']]);
        $author = $review->user;
        $author->forceFill(['role' => 'moderator', 'is_active' => false])->save();
        $this->review(['tmdb_id' => 7, 'user_id' => $author->id, 'is_approved' => true, 'content' => null, 'sentiment' => null]);
        $this->review(['tmdb_id' => 8, 'user_id' => $author->id, 'content' => null, 'sentiment' => null, 'removed_at' => now()]);
        $otherOfAuthor = $this->review(['tmdb_id' => 9, 'user_id' => $author->id]);
        $review->votes()->create(['user_id' => $this->admin()->id]);
        $reporter = User::factory()->create(['name' => 'Rita Reporter', 'username' => 'rita']);
        $staff = $this->admin();
        $open = Report::factory()->forReview($review)->create(['reporter_id' => $reporter->id, 'reason' => 'Spam', 'details' => 'Looks like an advert.']);
        $closed = Report::factory()->resolved()->forReview($review)->create([
            'reason' => 'Links', 'resolved_by' => $staff->id, 'resolution_note' => 'Checked, fine.',
        ]);
        Report::factory()->forReview($otherOfAuthor)->create();
        Report::factory()->resolved()->forReview($otherOfAuthor)->create();

        $response = $this->as($staff)->getJson("/api/admin/reviews/{$review->id}")->assertOk();

        $response
            ->assertJsonPath('review.id', $review->id)
            ->assertJsonPath('review.content', $text)
            ->assertJsonPath('review.status', 'flagged')
            ->assertJsonPath('review.is_approved', true)
            ->assertJsonPath('review.has_spoilers', true)
            ->assertJsonPath('review.sentiment', 'positive')
            ->assertJsonPath('review.moderation_labels', ['spoiler'])
            ->assertJsonPath('review.helpful_votes', 1)
            ->assertJsonPath('review.removed_at', null)
            ->assertJsonPath('review.reports_count', 2)
            ->assertJsonPath('review.reasons', ['Spam', 'Links'])
            ->assertJsonPath('review.movie.title', 'Fight Club')
            ->assertJsonPath('review.author', [
                'id' => $author->id,
                'name' => $author->name,
                'username' => $author->username,
                'role' => 'moderator',
                'is_active' => false,
                'reviews_with_text' => 2,
                'removed_reviews' => 1,
                'open_reports_against_their_reviews' => 2,
            ])
            ->assertJsonCount(2, 'review.reports');
        $this->assertSame(
            ['id' => $open->id, 'status' => 'open', 'reason' => 'Spam', 'details' => 'Looks like an advert.', 'reporter' => ['id' => $reporter->id, 'name' => 'Rita Reporter', 'username' => 'rita'], 'resolved_by' => null, 'resolution_note' => null],
            collect($response->json('review.reports.0'))->only(['id', 'status', 'reason', 'details', 'reporter', 'resolved_by', 'resolution_note'])->all(),
        );
        $response
            ->assertJsonPath('review.reports.1.id', $closed->id)
            ->assertJsonPath('review.reports.1.status', 'resolved')
            ->assertJsonPath('review.reports.1.resolved_by', ['id' => $staff->id, 'name' => $staff->name, 'username' => $staff->username])
            ->assertJsonPath('review.reports.1.resolution_note', 'Checked, fine.');
    }

    public function test_the_detail_of_a_removed_review_shows_when_it_was_removed(): void
    {
        $this->freezeTime();
        $review = $this->review(['content' => null, 'sentiment' => null, 'is_approved' => true, 'removed_at' => now()]);

        $this->as($this->admin())->getJson("/api/admin/reviews/{$review->id}")
            ->assertOk()
            ->assertJsonPath('review.status', 'removed')
            ->assertJsonPath('review.content', null)
            ->assertJsonPath('review.removed_at', $review->fresh()->removed_at->toJSON())
            ->assertJsonPath('review.author.removed_reviews', 1)
            ->assertJsonPath('review.reports', []);
    }

    public function test_the_detail_needs_the_same_number_of_queries_however_many_reports_there_are(): void
    {
        $admin = $this->admin();
        $few = $this->review(['tmdb_id' => 1]);
        $many = $this->review(['tmdb_id' => 2]);
        Report::factory()->forReview($few)->create();
        Report::factory()->count(8)->forReview($many)->create();
        Report::factory()->count(3)->resolved()->forReview($many)->create(['resolved_by' => $admin->id]);
        $this->as($admin);

        $count = function (Review $review): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson("/api/admin/reviews/{$review->id}")->assertOk();

            return count(DB::getQueryLog());
        };

        $this->assertSame($count($few), $count($many));
    }

    public function test_the_detail_of_a_missing_review_is_not_found_and_members_are_refused(): void
    {
        $review = $this->review();

        $this->as($this->admin())->getJson('/api/admin/reviews/9999')->assertNotFound();
        $this->getJson('/api/admin/reviews/abc')->assertNotFound();
        $this->as($this->moderator())->getJson("/api/admin/reviews/{$review->id}")->assertOk();
        $this->as(User::factory()->create())->getJson("/api/admin/reviews/{$review->id}")->assertForbidden();
    }

    public function test_the_list_can_be_narrowed_to_one_author_or_one_title(): void
    {
        $mine = $this->review(['tmdb_id' => 550, 'media_type' => 'movie']);
        $sameAuthor = $this->review(['user_id' => $mine->user_id, 'tmdb_id' => 1396, 'media_type' => 'tv']);
        $sameIdOtherType = $this->review(['tmdb_id' => 1396, 'media_type' => 'movie']);
        $this->review(['tmdb_id' => 7]);
        $admin = $this->admin();

        $ids = fn (string $query): array => collect($this->as($admin)->getJson("/api/admin/reviews?{$query}")->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame([$mine->id, $sameAuthor->id], $ids("user_id={$mine->user_id}"));
        $this->assertSame([$sameAuthor->id, $sameIdOtherType->id], $ids('tmdb_id=1396'));
        $this->assertSame([$sameAuthor->id], $ids('tmdb_id=1396&media_type=tv'));
        $this->assertSame([$sameIdOtherType->id], $ids('tmdb_id=1396&media_type=movie'));
        $this->assertSame([$mine->id], $ids("user_id={$mine->user_id}&tmdb_id=550"));
        $this->assertSame([], $ids('user_id=99999'));
    }

    public function test_the_new_filters_keep_the_summary_whole_site_and_combine_with_the_status(): void
    {
        $flagged = $this->review(['tmdb_id' => 1, 'is_approved' => true]);
        Report::factory()->forReview($flagged)->create();
        $other = $this->review(['tmdb_id' => 2, 'is_approved' => true]);
        Report::factory()->forReview($other)->create();

        $this->as($this->admin())->getJson("/api/admin/reviews?status=flagged&user_id={$flagged->user_id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $flagged->id)
            ->assertJsonPath('summary.flagged', 2);
        $this->getJson("/api/admin/reviews?status=pending&user_id={$flagged->user_id}")->assertJsonPath('meta.total', 0);
    }

    public function test_the_new_filters_are_validated(): void
    {
        $this->as($this->admin());

        foreach (['user_id=abc', 'user_id=0', 'user_id[]=1', 'tmdb_id=x', 'tmdb_id=-3', 'media_type=book', 'media_type[]=tv'] as $filter) {
            $this->getJson("/api/admin/reviews?{$filter}")->assertUnprocessable();
        }
    }

    public function test_a_spoiler_flag_alone_is_applied_recorded_and_changes_nothing_else(): void
    {
        $moderator = $this->moderator();
        $review = $this->review(['is_approved' => false]);
        Report::factory()->forReview($review)->create();
        $stats = $this->countStatsInvalidations();

        $this->as($moderator)->patchJson("/api/admin/reviews/{$review->id}", ['has_spoilers' => true])
            ->assertOk()
            ->assertJsonPath('review.has_spoilers', true)
            ->assertJsonPath('review.status', 'flagged')
            ->assertJsonPath('review.content', 'Great twist.');

        $review->refresh();
        $this->assertTrue($review->has_spoilers);
        $this->assertFalse($review->is_approved);
        $this->assertNull(Report::first()->resolved_at);
        $this->assertSame(0, $stats());
        [$entry] = $this->entries();
        $this->assertCount(1, $this->entries());
        $this->assertSame(AdminAction::ReviewSpoilerChanged, $entry->action);
        $this->assertSame($review->id, $entry->subject_id);
        $this->assertSame('Review by '.$review->user->username, $entry->subject_label);
        $this->assertSame(['has_spoilers' => ['from' => false, 'to' => true]], $entry->changes);
        $this->assertSame($moderator->id, $entry->actor_id);

        $this->patchJson("/api/admin/reviews/{$review->id}", ['has_spoilers' => false])->assertOk()->assertJsonPath('review.has_spoilers', false);
        $this->assertSame(['has_spoilers' => ['from' => true, 'to' => false]], $this->entries()[1]->changes);
    }

    public function test_setting_the_flag_it_already_has_records_nothing(): void
    {
        $review = $this->review(['has_spoilers' => true]);

        $this->as($this->admin())->patchJson("/api/admin/reviews/{$review->id}", ['has_spoilers' => true])
            ->assertOk()->assertJsonPath('review.has_spoilers', true);

        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_a_decision_and_a_spoiler_flag_together_write_both_entries_in_one_transaction(): void
    {
        $review = $this->review(['is_approved' => false]);
        Report::factory()->forReview($review)->create();

        $this->as($this->admin())->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'approved', 'has_spoilers' => true])
            ->assertOk()
            ->assertJsonPath('review.status', 'approved')
            ->assertJsonPath('review.has_spoilers', true);

        $this->assertSame([AdminAction::ReviewApproved, AdminAction::ReviewSpoilerChanged], array_map(fn (AdminAuditLog $entry): AdminAction => $entry->action, $this->entries()));
        $this->assertNotNull(Report::first()->resolved_at);
    }

    public function test_a_failing_spoiler_entry_undoes_the_decision_beside_it_and_tells_nobody(): void
    {
        $review = $this->review(['content' => 'Keep me.']);
        Report::factory()->forReview($review)->create();
        $this->failAuditInsertNumber(2);
        $stats = $this->countStatsInvalidations();

        $this->as($this->admin())->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'approved', 'has_spoilers' => true])->assertStatus(500);

        $review->refresh();
        $this->assertFalse($review->is_approved);
        $this->assertFalse($review->has_spoilers);
        $this->assertNull(Report::first()->resolved_at);
        $this->assertDatabaseCount('admin_audit_logs', 0);
        $this->assertSame(0, $stats());
    }

    public function test_a_failing_audit_entry_keeps_a_spoiler_flag_from_being_saved(): void
    {
        $review = $this->review();
        $this->failAuditInserts();

        $this->as($this->admin())->patchJson("/api/admin/reviews/{$review->id}", ['has_spoilers' => true])->assertStatus(500);

        $this->assertFalse($review->fresh()->has_spoilers);
    }

    public function test_removing_the_text_and_flagging_spoilers_in_one_request_is_refused_whole(): void
    {
        $review = $this->review(['is_approved' => true]);

        $this->as($this->admin())->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'rejected', 'has_spoilers' => true])
            ->assertStatus(409);

        $this->assertSame('Great twist.', $review->fresh()->content);
        $this->assertNull($review->fresh()->removed_at);
        $this->assertDatabaseCount('admin_audit_logs', 0);
        $this->assertSame(0, Notification::count());
    }

    public function test_a_review_without_text_refuses_the_spoiler_flag_but_accepts_clearing_it(): void
    {
        $removed = $this->review(['content' => null, 'sentiment' => null, 'is_approved' => true, 'removed_at' => now()]);
        $stars = $this->review(['tmdb_id' => 2, 'content' => null, 'sentiment' => null, 'is_approved' => true]);
        $this->as($this->admin());

        foreach ([$removed, $stars] as $review) {
            $this->patchJson("/api/admin/reviews/{$review->id}", ['has_spoilers' => true])
                ->assertStatus(409)
                ->assertJsonPath('message', 'This review has no text to mark as containing spoilers.');
            $this->patchJson("/api/admin/reviews/{$review->id}", ['has_spoilers' => false])
                ->assertOk()
                ->assertJsonPath('review.has_spoilers', false);
            $this->assertFalse($review->fresh()->has_spoilers);
        }

        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_rejecting_with_the_flag_cleared_just_rejects(): void
    {
        $review = $this->review(['is_approved' => true, 'has_spoilers' => true]);

        $this->as($this->admin())->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'rejected', 'has_spoilers' => false])
            ->assertOk()
            ->assertJsonPath('review.status', 'removed')
            ->assertJsonPath('review.has_spoilers', false);

        $this->assertNotNull($review->fresh()->removed_at);
        $this->assertSame([AdminAction::ReviewRejected], array_map(fn (AdminAuditLog $entry): AdminAction => $entry->action, $this->entries()));
    }

    public function test_rejecting_an_already_removed_review_leaves_its_row_untouched(): void
    {
        $removedAt = now()->subDays(3)->startOfSecond();
        $review = $this->review(['content' => null, 'sentiment' => null, 'is_approved' => true, 'removed_at' => $removedAt]);
        $review->votes()->create(['user_id' => $this->admin()->id]);
        $this->as($this->admin());

        $this->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'rejected'])->assertOk()->assertJsonPath('review.status', 'removed');
        $this->postJson('/api/admin/reviews/bulk', ['ids' => [$review->id], 'status' => 'rejected'])
            ->assertOk()
            ->assertJsonPath('results.0.reason', 'no_change');

        $this->assertTrue($removedAt->equalTo($review->fresh()->removed_at));
        $this->assertSame(1, $review->votes()->count());
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_the_update_needs_a_decision_or_a_spoiler_flag_and_both_are_validated(): void
    {
        $review = $this->review();
        $this->as($this->admin());

        foreach ([[], ['status' => null], ['has_spoilers' => null], ['status' => 'maybe'], ['has_spoilers' => 'sometimes'], ['status' => 'approved', 'has_spoilers' => []]] as $body) {
            $this->patchJson("/api/admin/reviews/{$review->id}", $body)->assertUnprocessable();
        }

        $this->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'approved', 'has_spoilers' => null])->assertOk();
        $this->patchJson("/api/admin/reviews/{$review->id}", ['has_spoilers' => 1])->assertOk()->assertJsonPath('review.has_spoilers', true);
        $this->patchJson('/api/admin/reviews/9999', ['has_spoilers' => true])->assertNotFound();
    }

    public function test_a_bulk_decision_reports_each_review_and_applies_what_the_single_request_would(): void
    {
        $this->freezeTime();
        $admin = $this->admin();
        $pending = $this->review(['tmdb_id' => 1]);
        $reported = $this->review(['tmdb_id' => 2, 'is_approved' => true]);
        Report::factory()->count(2)->forReview($reported)->create();
        $already = $this->review(['tmdb_id' => 3, 'is_approved' => true]);
        $stats = $this->countStatsInvalidations();

        $this->as($admin)->postJson('/api/admin/reviews/bulk', ['ids' => [$pending->id, 9999, $reported->id, $already->id], 'status' => 'approved'])
            ->assertOk()
            ->assertExactJson([
                'results' => [
                    ['id' => $pending->id, 'status' => 'approved', 'reason' => null],
                    ['id' => 9999, 'status' => 'skipped', 'reason' => 'not_found'],
                    ['id' => $reported->id, 'status' => 'approved', 'reason' => null],
                    ['id' => $already->id, 'status' => 'skipped', 'reason' => 'no_change'],
                ],
                'applied' => 2,
            ]);

        $this->assertTrue($pending->fresh()->is_approved);
        $this->assertSame(0, Report::query()->open()->count());
        $entries = $this->entries();
        $this->assertCount(2, $entries);
        $this->assertSame(['status' => ['from' => 'pending', 'to' => 'approved'], 'reports_resolved' => 0], $entries[0]->changes);
        $this->assertSame(['status' => ['from' => 'approved', 'to' => 'approved'], 'reports_resolved' => 2], $entries[1]->changes);
        $this->assertSame(1, $stats());
        $this->assertSame(0, Notification::count());
    }

    public function test_a_bulk_rejection_removes_text_notifies_authors_and_audits_like_the_single_request(): void
    {
        $moderator = $this->moderator();
        $withText = $this->review(['tmdb_id' => 1, 'is_approved' => true]);
        Report::factory()->forReview($withText)->create();
        $stars = $this->review(['tmdb_id' => 2, 'content' => null, 'sentiment' => null, 'is_approved' => true, 'removed_at' => now()]);
        $single = $this->review(['tmdb_id' => 3]);
        $stats = $this->countStatsInvalidations();

        $this->as($moderator)->postJson('/api/admin/reviews/bulk', ['ids' => [$withText->id, $stars->id], 'status' => 'rejected'])
            ->assertOk()
            ->assertJsonPath('applied', 1)
            ->assertJsonPath('results.0.status', 'rejected')
            ->assertJsonPath('results.1', ['id' => $stars->id, 'status' => 'skipped', 'reason' => 'no_change']);
        $this->patchJson("/api/admin/reviews/{$single->id}", ['status' => 'rejected'])->assertOk();

        $this->assertNotNull($withText->fresh()->removed_at);
        $this->assertNull($withText->fresh()->content);
        $this->assertNotNull(Report::first()->resolved_at);
        $this->assertSame(1, Notification::where('user_id', $withText->user_id)->where('type', Notification::REVIEW_REMOVED)->count());
        $this->assertSame(0, Notification::where('user_id', $stars->user_id)->count());
        [$bulkEntry, $singleEntry] = $this->entries();
        $this->assertSame(AdminAction::ReviewRejected, $bulkEntry->action);
        $this->assertSame($moderator->id, $bulkEntry->actor_id);
        $this->assertSame('Review by '.$withText->user->username, $bulkEntry->subject_label);
        $this->assertSame(['status' => ['from' => 'approved', 'to' => 'removed'], 'reports_resolved' => 1], $bulkEntry->changes);
        $this->assertSame(['status' => ['from' => 'pending', 'to' => 'removed'], 'reports_resolved' => 0], $singleEntry->changes);
        $this->assertSame(2, $stats());
    }

    public function test_one_review_that_fails_never_undoes_the_others(): void
    {
        $admin = $this->admin();
        $first = $this->review(['tmdb_id' => 1]);
        $second = $this->review(['tmdb_id' => 2, 'content' => 'Keep me.']);
        Report::factory()->forReview($second)->create();
        $third = $this->review(['tmdb_id' => 3]);
        $this->failAuditInsertNumber(2);
        $stats = $this->countStatsInvalidations();

        $this->as($admin)->postJson('/api/admin/reviews/bulk', ['ids' => [$first->id, $second->id, $third->id], 'status' => 'rejected'])
            ->assertOk()
            ->assertExactJson([
                'results' => [
                    ['id' => $first->id, 'status' => 'rejected', 'reason' => null],
                    ['id' => $second->id, 'status' => 'skipped', 'reason' => 'failed'],
                    ['id' => $third->id, 'status' => 'rejected', 'reason' => null],
                ],
                'applied' => 2,
            ]);

        $this->assertNotNull($first->fresh()->removed_at);
        $this->assertNotNull($third->fresh()->removed_at);
        $this->assertSame('Keep me.', $second->fresh()->content);
        $this->assertNull($second->fresh()->removed_at);
        $this->assertNull(Report::first()->resolved_at);
        $this->assertCount(2, $this->entries());
        $this->assertSame(0, Notification::where('user_id', $second->user_id)->count());
        $this->assertSame(2, Notification::count());
        $this->assertSame(1, $stats());
    }

    public function test_a_bulk_request_that_changes_nothing_still_answers_for_every_review(): void
    {
        $approved = $this->review(['is_approved' => true]);
        $stats = $this->countStatsInvalidations();

        $this->as($this->admin())->postJson('/api/admin/reviews/bulk', ['ids' => [$approved->id], 'status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('applied', 0)
            ->assertJsonPath('results.0.reason', 'no_change');

        $this->assertDatabaseCount('admin_audit_logs', 0);
        $this->assertSame(1, $stats());
    }

    public function test_a_bulk_request_takes_at_most_fifty_distinct_ids_and_a_decision(): void
    {
        $review = $this->review();
        $this->as($this->admin());
        $fifty = range(1001, 1050);

        $this->postJson('/api/admin/reviews/bulk', ['ids' => $fifty, 'status' => 'approved'])->assertOk()->assertJsonPath('applied', 0);
        $this->postJson('/api/admin/reviews/bulk', ['ids' => range(1001, 1051), 'status' => 'approved'])->assertUnprocessable();

        foreach ([
            [],
            ['ids' => [$review->id]],
            ['status' => 'approved'],
            ['ids' => [], 'status' => 'approved'],
            ['ids' => [$review->id, $review->id], 'status' => 'approved'],
            ['ids' => ['x'], 'status' => 'approved'],
            ['ids' => [0], 'status' => 'approved'],
            ['ids' => $review->id, 'status' => 'approved'],
            ['ids' => [$review->id], 'status' => 'maybe'],
        ] as $body) {
            $this->postJson('/api/admin/reviews/bulk', $body)->assertUnprocessable();
        }

        $this->assertFalse($review->fresh()->is_approved);
    }

    public function test_only_staff_may_use_the_review_tools(): void
    {
        $review = $this->review();
        $member = User::factory()->create();

        $this->getJson("/api/admin/reviews/{$review->id}")->assertUnauthorized();
        $this->postJson('/api/admin/reviews/bulk', ['ids' => [$review->id], 'status' => 'approved'])->assertUnauthorized();

        $this->as($member);
        $this->patchJson("/api/admin/reviews/{$review->id}", ['has_spoilers' => true])->assertForbidden();
        $this->postJson('/api/admin/reviews/bulk', ['ids' => [$review->id], 'status' => 'approved'])->assertForbidden();

        $this->as($this->moderator())->postJson('/api/admin/reviews/bulk', ['ids' => [$review->id], 'status' => 'approved'])->assertOk()->assertJsonPath('applied', 1);
        $this->as($this->admin())->postJson('/api/admin/reviews/bulk', ['ids' => [$review->id], 'status' => 'approved'])->assertOk()->assertJsonPath('applied', 0);
        $this->assertCount(1, $this->entries());
    }
}
