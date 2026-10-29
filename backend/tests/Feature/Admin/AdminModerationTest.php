<?php

namespace Tests\Feature\Admin;

use App\Models\LocalMovie;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Models\User;

class AdminModerationTest extends AdminTestCase
{
    private function report(Review $review, string $reason = 'Spam', bool $resolved = false): ReviewReport
    {
        $factory = ReviewReport::factory();

        return ($resolved ? $factory->resolved() : $factory)->create(['review_id' => $review->id, 'reason' => $reason]);
    }

    private function moderator(): User
    {
        return User::factory()->create(['role' => 'moderator']);
    }

    public function test_reported_reviews_are_flagged_with_their_reports_and_film(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 550, 'title' => 'Fight Club']);
        $review = $this->review(['is_approved' => true, 'moderation_labels' => ['spam']]);
        $this->report($review, 'Spam');
        $this->report($review, 'Links');
        $this->report($review, 'Spam');
        $this->review(['tmdb_id' => 2, 'is_approved' => true]);

        $this->as($this->admin())->getJson('/api/admin/reviews?status=flagged')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $review->id)
            ->assertJsonPath('data.0.status', 'flagged')
            ->assertJsonPath('data.0.reports_count', 3)
            ->assertJsonPath('data.0.reasons', ['Spam', 'Links'])
            ->assertJsonPath('data.0.moderation_labels', ['spam'])
            ->assertJsonPath('data.0.rating', 4)
            ->assertJsonPath('data.0.sentiment', 'positive')
            ->assertJsonPath('data.0.movie.title', 'Fight Club')
            ->assertJsonPath('summary.flagged', 1);
    }

    public function test_the_other_statuses_split_the_queue_without_overlap(): void
    {
        $pending = $this->review(['tmdb_id' => 1, 'is_approved' => false]);
        $approved = $this->review(['tmdb_id' => 2, 'is_approved' => true]);
        $flagged = $this->review(['tmdb_id' => 3, 'is_approved' => false]);
        $this->report($flagged);
        $this->report($approved, resolved: true);

        $admin = $this->admin();

        $this->as($admin)->getJson('/api/admin/reviews?status=pending')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $pending->id);
        $this->as($admin)->getJson('/api/admin/reviews?status=approved')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $approved->id)->assertJsonPath('data.0.status', 'approved');
        $this->as($admin)->getJson('/api/admin/reviews?status=flagged')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $flagged->id);
    }

    public function test_rejecting_marks_the_review_removed_and_closes_its_reports(): void
    {
        $review = $this->review(['is_approved' => true]);
        $this->report($review);

        $this->as($this->admin())->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'rejected'])
            ->assertOk()
            ->assertJsonPath('review.status', 'removed')
            ->assertJsonPath('review.reports_count', 1);

        $this->assertNotNull($review->fresh()->removed_at);
        $this->assertNotNull(ReviewReport::first()->resolved_at);

        $this->as($this->admin())->getJson('/api/admin/reviews?status=removed')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.content', null)
            ->assertJsonPath('summary.removed', 1)
            ->assertJsonPath('summary.flagged', 0);
    }

    public function test_approving_closes_the_reports(): void
    {
        $review = $this->review();
        $this->report($review);

        $this->as($this->admin())->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('review.status', 'approved');

        $this->assertNotNull(ReviewReport::first()->resolved_at);
    }

    public function test_the_summary_carries_the_sentiment_split(): void
    {
        $this->review(['tmdb_id' => 1, 'sentiment' => 'positive']);
        $this->review(['tmdb_id' => 2, 'sentiment' => 'positive']);
        $this->review(['tmdb_id' => 3, 'sentiment' => 'negative']);
        $this->review(['tmdb_id' => 4, 'sentiment' => 'neutral']);

        $this->as($this->admin())->getJson('/api/admin/reviews')
            ->assertJsonPath('summary.sentiment_total', 4)
            ->assertJsonPath('summary.sentiment.positive.count', 2)
            ->assertJsonPath('summary.sentiment.positive.percent', 50)
            ->assertJsonPath('summary.sentiment.negative.percent', 25);
    }

    public function test_moderators_can_moderate_reviews_but_not_manage_movies(): void
    {
        $review = $this->review();
        $moderator = $this->moderator();

        $this->as($moderator)->getJson('/api/admin/reviews')->assertOk();
        $this->as($moderator)->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'approved'])->assertOk();
        $this->as($moderator)->deleteJson("/api/admin/reviews/{$review->id}")->assertOk();

        $this->as($moderator)->getJson('/api/admin/movies')->assertForbidden();
    }

    public function test_filters_find_reported_reviews_beyond_the_first_page(): void
    {
        $this->freezeTime();
        $target = $this->review(['content' => 'Unwanted promotion', 'sentiment' => 'negative', 'is_approved' => true]);
        $this->report($target, 'Links');
        for ($id = 1; $id <= 21; $id++) {
            $this->report($this->review(['tmdb_id' => $id, 'is_approved' => true]));
        }

        $this->as($this->admin())->getJson('/api/admin/reviews?status=flagged')
            ->assertOk()->assertJsonMissing(['id' => $target->id, 'content' => $target->content]);

        foreach (['q=PROMOTION', 'sentiment=negative', 'reason=Links', 'q=promotion&sentiment=negative&reason=Links'] as $filter) {
            $this->getJson('/api/admin/reviews?status=flagged&'.$filter)
                ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $target->id);
        }
    }

    public function test_plain_members_cannot_reach_the_review_queue(): void
    {
        $this->as(User::factory()->create())->getJson('/api/admin/reviews')->assertForbidden();
    }
}
