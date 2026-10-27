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
