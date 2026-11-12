<?php

namespace Tests\Feature\Admin;

use App\Models\AdminAuditLog;
use App\Models\LocalMovie;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class AdminRatingRemovalTest extends AdminRatingTestCase
{
    public function test_a_star_rating_is_deleted_and_the_aggregates_follow(): void
    {
        $rating = $this->rating(['rating' => 1]);
        $keep = $this->rating(['rating' => 5]);

        $this->as($this->admin())->deleteJson("/api/admin/ratings/{$rating->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Rating deleted.');

        $this->assertModelMissing($rating);
        $this->assertModelExists($keep);
        $this->getJson('/api/admin/ratings/films/550')->assertJsonPath('total', 1)->assertJsonPath('average', 5);
        $this->getJson('/api/admin/ratings')->assertJsonPath('summary.total', 1);
    }

    public function test_the_members_public_summary_follows_the_deletion(): void
    {
        $rating = $this->rating(['rating' => 1]);
        $this->rating(['rating' => 5]);
        $viewer = User::factory()->create();

        $this->as($viewer)->getJson('/api/reviews?tmdb_id=550')->assertJsonPath('summary.average', 3);
        $this->as($this->admin())->deleteJson("/api/admin/ratings/{$rating->id}")->assertOk();

        $this->as($viewer)->getJson('/api/reviews?tmdb_id=550')->assertJsonPath('summary.average', 5)->assertJsonPath('summary.ratings', 1);
    }

    public function test_a_moderator_may_delete_a_rating(): void
    {
        $rating = $this->rating();

        $this->as($this->moderator())->deleteJson("/api/admin/ratings/{$rating->id}")->assertOk();

        $this->assertModelMissing($rating);
    }

    public function test_the_deletion_is_recorded_with_the_stars_the_film_and_the_actor(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 550, 'title' => 'Fight Club']);
        $rating = $this->rating(['rating' => 2]);
        $tv = $this->rating(['media_type' => 'tv', 'tmdb_id' => 1399]);
        $unknown = $this->rating(['tmdb_id' => 777]);
        $moderator = $this->moderator();

        $this->as($moderator);
        foreach ([$rating, $tv, $unknown] as $row) {
            $this->deleteJson("/api/admin/ratings/{$row->id}")->assertOk();
        }

        $entries = AdminAuditLog::query()->orderBy('id')->get();
        $this->assertCount(3, $entries);
        $this->assertSame('rating.deleted', $entries[0]->action->value);
        $this->assertSame('rating', $entries[0]->subject_type->value);
        $this->assertSame($rating->id, $entries[0]->subject_id);
        $this->assertSame("Rating by {$rating->user->username} on Fight Club", $entries[0]->subject_label);
        $this->assertSame(['rating' => ['from' => 2, 'to' => null], 'reports_deleted' => 0], $entries[0]->changes);
        $this->assertSame($moderator->id, $entries[0]->actor_id);
        $this->assertSame('moderator', $entries[0]->actor_role);
        $this->assertSame("Rating by {$tv->user->username} on tv-1399", $entries[1]->subject_label);
        $this->assertSame("Rating by {$unknown->user->username} on 777", $entries[2]->subject_label);
    }

    public function test_the_reports_filed_against_the_rating_go_with_it(): void
    {
        $rating = $this->rating();
        $other = $this->review();
        Report::factory()->forReview($rating)->create();
        Report::factory()->forReview($other)->create();

        $this->as($this->admin())->deleteJson("/api/admin/ratings/{$rating->id}")->assertOk();

        $this->assertDatabaseCount('reports', 1);
        $this->assertSame($other->id, Report::first()->target_id);
    }

    public function test_a_review_with_text_is_refused_and_nothing_changes(): void
    {
        $review = $this->review(['content' => 'Great twist.']);

        $this->as($this->admin())->deleteJson("/api/admin/ratings/{$review->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This is a review with text, not a star rating. Use review moderation to remove it.');

        $this->assertModelExists($review);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_an_unknown_id_is_a_404_and_writes_nothing(): void
    {
        $this->as($this->admin())->deleteJson('/api/admin/ratings/999')->assertNotFound();
        $this->deleteJson('/api/admin/ratings/abc')->assertNotFound();

        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_only_staff_may_delete(): void
    {
        $rating = $this->rating();

        $this->deleteJson("/api/admin/ratings/{$rating->id}")->assertUnauthorized();
        $this->as(User::factory()->create())->deleteJson("/api/admin/ratings/{$rating->id}")->assertForbidden();

        $this->assertModelExists($rating);
    }

    public function test_the_member_is_told_nothing(): void
    {
        $rating = $this->rating();

        $this->as($this->admin())->deleteJson("/api/admin/ratings/{$rating->id}")->assertOk();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_deleting_a_rating_retires_the_cached_stats(): void
    {
        $admin = $this->admin();
        $rating = $this->rating();
        $today = now()->toDateString();

        $this->as($admin)->getJson('/api/admin/stats?days=7')->assertJsonPath('activity.6.date', $today)->assertJsonPath('activity.6.ratings', 1);
        $this->deleteJson("/api/admin/ratings/{$rating->id}")->assertOk();

        $this->getJson('/api/admin/stats?days=7')->assertJsonPath('activity.6.ratings', 0);
    }

    public function test_a_failing_audit_insert_rolls_the_deletion_back(): void
    {
        $rating = $this->rating();
        $this->failAuditInserts();

        $this->as($this->admin())->deleteJson("/api/admin/ratings/{$rating->id}")->assertStatus(500);

        $this->assertModelExists($rating);
    }

    public function test_the_purge_removes_every_star_rating_of_the_member_and_keeps_reviews(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create();
        foreach ([1, 2, 3] as $film) {
            $this->rating(['user_id' => $member->id, 'tmdb_id' => $film]);
        }
        $this->rating(['user_id' => $member->id, 'tmdb_id' => 4, 'media_type' => 'tv']);
        $review = $this->review(['user_id' => $member->id, 'tmdb_id' => 9]);
        $theirs = $this->rating(['user_id' => $other->id]);

        $this->as($this->admin())->deleteJson("/api/admin/users/{$member->id}/ratings")
            ->assertOk()
            ->assertJsonPath('removed', 4)
            ->assertJsonPath('message', '4 ratings removed.');

        $this->assertSame([$review->id], Review::query()->where('user_id', $member->id)->pluck('id')->all());
        $this->assertModelExists($theirs);
        $this->assertModelExists($member);
    }

    public function test_the_purge_is_recorded_once_with_the_count(): void
    {
        $member = User::factory()->create(['username' => 'bomber']);
        foreach ([1, 2, 3] as $film) {
            $this->rating(['user_id' => $member->id, 'tmdb_id' => $film]);
        }
        $admin = $this->admin();

        $this->as($admin)->deleteJson("/api/admin/users/{$member->id}/ratings")->assertOk();

        $entry = AdminAuditLog::query()->sole();
        $this->assertSame('rating.purged', $entry->action->value);
        $this->assertSame('user', $entry->subject_type->value);
        $this->assertSame($member->id, $entry->subject_id);
        $this->assertSame('Ratings by bomber', $entry->subject_label);
        $this->assertSame(['removed' => 3, 'reports_deleted' => 0], $entry->changes);
        $this->assertSame($admin->id, $entry->actor_id);
    }

    public function test_a_purge_that_removes_nothing_writes_nothing_and_leaves_the_cache_alone(): void
    {
        $member = User::factory()->create();
        $this->review(['user_id' => $member->id]);
        $this->as($this->admin())->getJson('/api/admin/stats')->assertOk();
        $version = Cache::get('admin:stats:version', 'initial');

        $this->deleteJson("/api/admin/users/{$member->id}/ratings")
            ->assertOk()
            ->assertJsonPath('removed', 0)
            ->assertJsonPath('message', '0 ratings removed.');

        $this->assertDatabaseCount('admin_audit_logs', 0);
        $this->assertSame($version, Cache::get('admin:stats:version', 'initial'));
    }

    public function test_one_rating_is_worded_in_the_singular(): void
    {
        $member = User::factory()->create();
        $this->rating(['user_id' => $member->id]);

        $this->as($this->admin())->deleteJson("/api/admin/users/{$member->id}/ratings")->assertJsonPath('message', '1 rating removed.');
    }

    public function test_a_purge_deletes_the_reports_of_the_removed_ratings_only(): void
    {
        $member = User::factory()->create();
        $removed = $this->rating(['user_id' => $member->id]);
        $kept = $this->review(['user_id' => $member->id, 'tmdb_id' => 2]);
        Report::factory()->forReview($removed)->create();
        Report::factory()->forReview($kept)->create();

        $this->as($this->admin())->deleteJson("/api/admin/users/{$member->id}/ratings")->assertOk();

        $this->assertDatabaseCount('reports', 1);
        $this->assertSame($kept->id, Report::first()->target_id);
    }

    public function test_a_purge_retires_the_cached_stats(): void
    {
        $member = User::factory()->create();
        $this->rating(['user_id' => $member->id]);
        $this->rating(['user_id' => $member->id, 'tmdb_id' => 2]);

        $this->as($this->admin())->getJson('/api/admin/stats?days=7')->assertJsonPath('activity.6.ratings', 2);
        $this->deleteJson("/api/admin/users/{$member->id}/ratings")->assertOk();

        $this->getJson('/api/admin/stats?days=7')->assertJsonPath('activity.6.ratings', 0);
    }

    public function test_a_moderator_may_purge_plain_members_only(): void
    {
        $moderator = $this->moderator();
        $member = User::factory()->create();
        $peer = $this->moderator();
        $admin = $this->admin();
        $this->rating(['user_id' => $member->id]);
        $peerRating = $this->rating(['user_id' => $peer->id, 'tmdb_id' => 2]);
        $adminRating = $this->rating(['user_id' => $admin->id, 'tmdb_id' => 3]);

        $this->as($moderator);
        $this->deleteJson("/api/admin/users/{$peer->id}/ratings")->assertForbidden()
            ->assertJsonPath('message', 'Moderators cannot manage admin or moderator accounts.');
        $this->deleteJson("/api/admin/users/{$admin->id}/ratings")->assertForbidden();
        $this->assertModelExists($peerRating);
        $this->assertModelExists($adminRating);
        $this->assertDatabaseCount('admin_audit_logs', 0);

        $this->deleteJson("/api/admin/users/{$member->id}/ratings")->assertOk()->assertJsonPath('removed', 1);
    }

    public function test_an_admin_may_purge_staff_but_nobody_may_purge_themselves(): void
    {
        $admin = $this->admin();
        $moderator = $this->moderator();
        $this->rating(['user_id' => $moderator->id]);
        $own = $this->rating(['user_id' => $admin->id, 'tmdb_id' => 2]);
        $ownModeratorRating = $this->rating(['user_id' => $moderator->id, 'tmdb_id' => 3]);

        $this->as($admin)->deleteJson("/api/admin/users/{$admin->id}/ratings")->assertForbidden()
            ->assertJsonPath('message', 'You cannot remove your own ratings from the admin area.');
        $this->assertModelExists($own);

        $this->as($moderator)->deleteJson("/api/admin/users/{$moderator->id}/ratings")->assertForbidden();
        $this->assertModelExists($ownModeratorRating);

        $this->as($admin)->deleteJson("/api/admin/users/{$moderator->id}/ratings")->assertOk()->assertJsonPath('removed', 2);
    }

    public function test_the_purge_needs_staff_and_an_existing_member(): void
    {
        $member = User::factory()->create();
        $this->rating(['user_id' => $member->id]);

        $this->deleteJson("/api/admin/users/{$member->id}/ratings")->assertUnauthorized();
        $this->as(User::factory()->create())->deleteJson("/api/admin/users/{$member->id}/ratings")->assertForbidden();
        $this->as($this->admin())->deleteJson('/api/admin/users/9999/ratings')->assertNotFound();

        $this->assertSame(1, Review::query()->where('user_id', $member->id)->count());
    }

    public function test_a_failing_audit_insert_rolls_the_purge_back(): void
    {
        $member = User::factory()->create();
        $this->rating(['user_id' => $member->id]);
        $this->rating(['user_id' => $member->id, 'tmdb_id' => 2]);
        $this->failAuditInserts();

        $this->as($this->admin())->deleteJson("/api/admin/users/{$member->id}/ratings")->assertStatus(500);

        $this->assertSame(2, Review::query()->where('user_id', $member->id)->count());
    }

    public function test_a_failing_audit_insert_restores_the_reports_and_leaves_stats_and_log_alone(): void
    {
        $member = User::factory()->create();
        $rating = $this->rating(['user_id' => $member->id]);
        $other = $this->rating(['user_id' => $member->id, 'tmdb_id' => 2]);
        Report::factory()->forReview($rating)->create();
        Report::factory()->forReview($other)->create();
        $admin = $this->admin();
        $this->as($admin)->getJson('/api/admin/stats')->assertOk();
        $version = Cache::get('admin:stats:version', 'initial');
        $this->failAuditInserts();

        $this->deleteJson("/api/admin/ratings/{$rating->id}")->assertStatus(500);
        $this->deleteJson("/api/admin/users/{$member->id}/ratings")->assertStatus(500);

        $this->assertSame(2, Review::query()->where('user_id', $member->id)->count());
        $this->assertDatabaseCount('reports', 2);
        $this->assertSame($version, Cache::get('admin:stats:version', 'initial'));
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_the_reports_filed_against_deleted_ratings_are_counted_in_the_audit_entries(): void
    {
        $member = User::factory()->create();
        $single = $this->rating(['user_id' => $member->id]);
        $first = $this->rating(['user_id' => $member->id, 'tmdb_id' => 2]);
        $second = $this->rating(['user_id' => $member->id, 'tmdb_id' => 3]);
        Report::factory()->forReview($single)->create();
        Report::factory()->forReview($single)->create();
        Report::factory()->forReview($first)->create();

        $this->as($this->admin())->deleteJson("/api/admin/ratings/{$single->id}")->assertOk();
        $this->deleteJson("/api/admin/users/{$member->id}/ratings")->assertOk()->assertJsonPath('removed', 2);

        $entries = AdminAuditLog::query()->orderBy('id')->get();
        $this->assertSame(2, $entries[0]->changes['reports_deleted']);
        $this->assertSame(['removed' => 2, 'reports_deleted' => 1], $entries[1]->changes);
        $this->assertModelMissing($second);
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_the_stars_of_a_moderated_review_are_listed_but_never_deleted_by_the_rating_tools(): void
    {
        $member = User::factory()->create(['username' => 'maya']);
        $moderated = $this->rating(['user_id' => $member->id, 'tmdb_id' => 1, 'removed_at' => now()]);
        $plain = $this->rating(['user_id' => $member->id, 'tmdb_id' => 2]);
        Report::factory()->forReview($moderated)->create();
        Report::factory()->forReview($plain)->create();

        $this->as($this->admin())->getJson('/api/admin/ratings')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('data.0.id', $plain->id)
            ->assertJsonPath('data.0.moderated', false)
            ->assertJsonPath('data.1.id', $moderated->id)
            ->assertJsonPath('data.1.moderated', true);

        $this->deleteJson("/api/admin/ratings/{$moderated->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This row belongs to a moderated review, not a plain star rating. Use review moderation to remove it.');
        $this->assertModelExists($moderated);
        $this->assertDatabaseCount('admin_audit_logs', 0);

        $this->deleteJson("/api/admin/users/{$member->id}/ratings")->assertOk()->assertJsonPath('removed', 1);

        $this->assertModelExists($moderated);
        $this->assertModelMissing($plain);
        $this->assertDatabaseCount('reports', 1);
        $this->assertSame($moderated->id, Report::first()->target_id);
        $entry = AdminAuditLog::query()->sole();
        $this->assertSame(['removed' => 1, 'reports_deleted' => 1], $entry->changes);
    }

    public function test_a_purge_of_only_moderated_rows_removes_nothing_and_records_nothing(): void
    {
        $member = User::factory()->create();
        $moderated = $this->rating(['user_id' => $member->id, 'removed_at' => now()]);

        $this->as($this->admin())->deleteJson("/api/admin/users/{$member->id}/ratings")->assertOk()->assertJsonPath('removed', 0);

        $this->assertModelExists($moderated);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_empty_and_whitespace_only_content_are_classified_consistently(): void
    {
        $member = User::factory()->create();
        $empty = $this->rating(['user_id' => $member->id, 'tmdb_id' => 1, 'content' => '']);
        $blank = $this->review(['user_id' => $member->id, 'tmdb_id' => 2, 'content' => '   ', 'is_approved' => true]);
        $admin = $this->admin();

        $this->as($admin)->getJson('/api/admin/ratings')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $empty->id);
        $this->getJson('/api/admin/ratings/films/2')->assertJsonPath('total', 0)->assertJsonPath('with_text_count', 1);
        $this->deleteJson("/api/admin/ratings/{$blank->id}")->assertUnprocessable()
            ->assertJsonPath('message', 'This is a review with text, not a star rating. Use review moderation to remove it.');

        $this->deleteJson("/api/admin/users/{$member->id}/ratings")->assertOk()->assertJsonPath('removed', 1);

        $this->assertModelMissing($empty);
        $this->assertModelExists($blank);
    }
}
