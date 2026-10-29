<?php

namespace Tests\Feature\Review;

use App\Groq\InterfaceServices\GroqStructuredOutputInterface;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Models\User;
use Tests\Feature\Social\SocialTestCase;

class ReviewReportTest extends SocialTestCase
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function published(array $attributes = []): Review
    {
        return Review::create([
            'user_id' => User::factory()->create()->id,
            'tmdb_id' => 550,
            'rating' => 4,
            'content' => 'Great twist.',
            'sentiment' => 'positive',
            'is_approved' => true,
            ...$attributes,
        ]);
    }

    public function test_a_member_can_report_a_published_review(): void
    {
        $review = $this->published();
        $reporter = User::factory()->create();

        $this->as($reporter)->postJson("/api/reviews/{$review->id}/report", ['reason' => 'Spam'])
            ->assertCreated()
            ->assertJsonPath('reason', 'Spam');

        $this->assertDatabaseHas('review_reports', ['review_id' => $review->id, 'user_id' => $reporter->id, 'reason' => 'Spam', 'resolved_at' => null]);
    }

    public function test_a_review_by_an_author_hidden_from_the_reporter_cannot_be_reported(): void
    {
        $review = $this->published(['user_id' => User::factory()->create(['profile_visibility' => 'followers'])->id]);
        $reporter = User::factory()->create();

        $this->as($reporter)->postJson("/api/reviews/{$review->id}/report", ['reason' => 'Spam'])->assertForbidden();

        $this->assertSame(0, ReviewReport::count());
    }

    public function test_reporting_twice_keeps_one_report_and_changes_the_reason(): void
    {
        $review = $this->published();
        $reporter = User::factory()->create();

        $this->as($reporter)->postJson("/api/reviews/{$review->id}/report", ['reason' => 'Spam'])->assertCreated();
        $this->as($reporter)->postJson("/api/reviews/{$review->id}/report", ['reason' => 'Links'])->assertOk();

        $this->assertSame(1, ReviewReport::count());
        $this->assertSame('Links', ReviewReport::first()->reason);
    }

    public function test_the_reason_must_be_one_of_the_known_reasons(): void
    {
        $review = $this->published();
        $reporter = User::factory()->create();

        $this->as($reporter)->postJson("/api/reviews/{$review->id}/report", ['reason' => 'Boring'])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->as($reporter)->postJson("/api/reviews/{$review->id}/report", [])->assertUnprocessable();
    }

    public function test_you_cannot_report_your_own_unpublished_or_text_less_reviews(): void
    {
        $mine = $this->published();
        $unpublished = $this->published(['tmdb_id' => 2, 'is_approved' => false]);
        $starsOnly = $this->published(['tmdb_id' => 3, 'content' => null, 'sentiment' => null]);
        $removed = $this->published(['tmdb_id' => 4, 'removed_at' => now()]);

        $this->as($mine->user)->postJson("/api/reviews/{$mine->id}/report", ['reason' => 'Spam'])->assertForbidden();

        $reporter = User::factory()->create();
        foreach ([$unpublished, $starsOnly, $removed] as $review) {
            $this->as($reporter)->postJson("/api/reviews/{$review->id}/report", ['reason' => 'Spam'])->assertForbidden();
        }

        $this->assertSame(0, ReviewReport::count());
    }

    public function test_reporting_needs_a_signed_in_member_and_an_existing_review(): void
    {
        $review = $this->published();

        $this->postJson("/api/reviews/{$review->id}/report", ['reason' => 'Spam'])->assertUnauthorized();
        $this->as(User::factory()->create())->postJson('/api/reviews/9999/report', ['reason' => 'Spam'])->assertNotFound();
    }

    public function test_rewriting_a_review_clears_its_reports_and_removal(): void
    {
        $this->mock(GroqStructuredOutputInterface::class)->shouldReceive('askWithSchema')
            ->twice()->andReturn(['labels' => [], 'sentiment' => 'neutral']);
        $review = $this->published(['removed_at' => now()]);
        ReviewReport::factory()->create(['review_id' => $review->id]);

        $this->as($review->user)->putJson('/api/reviews/mine', ['tmdb_id' => 550, 'content' => 'Second thoughts.', 'rating' => 3])->assertOk();

        $this->assertSame(0, ReviewReport::count());
        $this->assertNull($review->fresh()->removed_at);
        $this->assertSame([], $review->fresh()->moderation_labels);
        $this->assertTrue($review->fresh()->is_approved);
    }
}
