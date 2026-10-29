<?php

namespace Tests\Feature\Admin;

use App\Models\Review;

class AdminReviewsTest extends AdminTestCase
{
    public function test_the_queue_lists_pending_reviews_with_text_by_default(): void
    {
        $pending = $this->review(['is_approved' => false]);
        $this->review(['tmdb_id' => 2, 'is_approved' => true]);
        $this->review(['tmdb_id' => 3, 'content' => null, 'sentiment' => null, 'is_approved' => true]);

        $this->as($this->admin())->getJson('/api/admin/reviews')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $pending->id)
            ->assertJsonPath('data.0.author.id', $pending->user_id);
    }

    public function test_approved_reviews_can_be_listed(): void
    {
        $this->review(['is_approved' => false]);
        $approved = $this->review(['tmdb_id' => 2, 'is_approved' => true]);

        $this->as($this->admin())->getJson('/api/admin/reviews?status=approved')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $approved->id);
    }

    public function test_the_status_filter_is_validated(): void
    {
        $this->as($this->admin())->getJson('/api/admin/reviews?status=spam')->assertUnprocessable();
    }

    public function test_filters_find_reviews_beyond_the_first_unfiltered_page(): void
    {
        $target = $this->review(['content' => 'Unwanted promotion', 'sentiment' => 'negative', 'moderation_labels' => ['spam', 'offensive language']]);
        for ($id = 1; $id <= 21; $id++) {
            $this->review(['tmdb_id' => $id]);
        }

        $this->as($this->admin())->getJson('/api/admin/reviews')
            ->assertJsonMissing(['id' => $target->id, 'content' => $target->content]);

        foreach (['q=PROMOTION', 'sentiment=negative', 'reason=spam', 'q=promotion&sentiment=negative&reason=spam'] as $filter) {
            $this->getJson('/api/admin/reviews?'.$filter)
                ->assertOk()->assertJsonPath('meta.total', 1)
                ->assertJsonPath('data.0.id', $target->id);
        }
    }

    public function test_search_matches_authors_and_treats_wildcards_literally(): void
    {
        $target = $this->review();
        $target->user->forceFill(['name' => 'Distinct Name', 'username' => 'unique_handle'])->save();
        $this->review(['tmdb_id' => 2, 'content' => 'uniqueXhandle']);

        foreach (['DISTINCT', 'unique_handle'] as $search) {
            $this->as($this->admin())->getJson('/api/admin/reviews?q='.$search)
                ->assertOk()->assertJsonPath('meta.total', 1)
                ->assertJsonPath('data.0.id', $target->id);
        }
    }

    public function test_filters_combine_with_status_and_distinguish_unclassified_reviews(): void
    {
        $target = $this->review(['is_approved' => true]);
        $this->review(['tmdb_id' => 2, 'is_approved' => true, 'moderation_labels' => []]);
        $this->review(['tmdb_id' => 3]);

        $this->as($this->admin())->getJson('/api/admin/reviews?status=approved&reason=awaiting%20classification')
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $target->id);
        $this->getJson('/api/admin/reviews?status=approved&sentiment=negative')
            ->assertOk()->assertJsonPath('meta.total', 0);
        foreach (['sentiment=invalid', 'reason=invalid', 'q[]=invalid'] as $filter) {
            $this->getJson('/api/admin/reviews?'.$filter)->assertUnprocessable();
        }
    }

    public function test_removing_an_automatically_approved_review_keeps_stars_and_clears_votes(): void
    {
        $review = $this->review(['is_approved' => true, 'moderation_labels' => [], 'rating' => 2]);
        $review->votes()->create(['user_id' => $this->admin()->id]);

        $this->as($this->admin())->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'rejected'])
            ->assertOk()->assertJsonPath('review.content', null);
        $review->refresh();
        $this->assertSame(2, $review->rating);
        $this->assertNull($review->moderation_labels);
        $this->assertSame(0, $review->votes()->count());
        $this->getJson('/api/admin/reviews?status=approved')->assertJsonPath('meta.total', 0);
    }

    public function test_approving_makes_a_review_visible(): void
    {
        $review = $this->review(['is_approved' => false]);

        $this->as($this->admin())->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('review.is_approved', true)
            ->assertJsonPath('review.content', 'Great twist.');

        $this->assertTrue($review->fresh()->is_approved);
    }

    public function test_rejecting_removes_the_text_but_keeps_the_star_rating(): void
    {
        $review = $this->review(['rating' => 2]);

        $this->as($this->admin())->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'rejected'])
            ->assertOk()
            ->assertJsonPath('review.content', null);

        $review->refresh();
        $this->assertNull($review->content);
        $this->assertNull($review->sentiment);
        $this->assertSame(2, $review->rating);
        $this->assertTrue($review->is_approved);
    }

    public function test_a_decision_is_required(): void
    {
        $review = $this->review();

        $this->as($this->admin())->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'maybe'])->assertUnprocessable();
        $this->as($this->admin())->patchJson("/api/admin/reviews/{$review->id}", [])->assertUnprocessable();
    }

    public function test_an_admin_can_delete_a_review(): void
    {
        $review = $this->review();

        $this->as($this->admin())->deleteJson("/api/admin/reviews/{$review->id}")->assertOk();

        $this->assertSame(0, Review::count());
    }

    public function test_an_unknown_review_is_not_found(): void
    {
        $this->as($this->admin())->patchJson('/api/admin/reviews/9999', ['status' => 'approved'])->assertNotFound();
    }
}
