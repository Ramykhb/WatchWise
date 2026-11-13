<?php

namespace Tests\Feature\Setting;

use App\Groq\Exceptions\GroqUnavailable;
use App\Groq\InterfaceServices\GroqStructuredOutputInterface;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Social\SocialTestCase;

/** How `reviews_require_approval` and `review_auto_moderation` decide whether review text starts pending or approved. */
class ReviewSettingsTest extends SocialTestCase
{
    use ChangesSettings;

    /** How many times the moderation (not the sentiment) was asked. */
    private int $moderationCalls = 0;

    /**
     * Groq answers the moderation with `$labels` (null = Groq is down) and the sentiment as positive.
     *
     * @param  list<string>|null  $labels
     */
    private function groqModeratesAs(?array $labels): void
    {
        $this->moderationCalls = 0;
        $this->app->instance(GroqStructuredOutputInterface::class, Mockery::mock(GroqStructuredOutputInterface::class, function ($mock) use ($labels): void {
            $mock->shouldReceive('askWithSchema')->andReturnUsing(function ($prompt, $name) use ($labels): array {
                if ($name !== 'review_moderation') {
                    return ['sentiment' => 'positive'];
                }

                $this->moderationCalls++;

                return $labels === null ? throw new GroqUnavailable('down') : ['labels' => $labels];
            });
        }));
    }

    /**
     * @return array<string, array{requireApproval: bool, autoModeration: bool, labels: list<string>|null, approved: bool, moderationCalls: int}>
     */
    public static function matrix(): array
    {
        return [
            'defaults, clean text' => ['requireApproval' => false, 'autoModeration' => true, 'labels' => [], 'approved' => true, 'moderationCalls' => 1],
            'defaults, a label' => ['requireApproval' => false, 'autoModeration' => true, 'labels' => ['spam'], 'approved' => false, 'moderationCalls' => 1],
            'defaults, Groq down' => ['requireApproval' => false, 'autoModeration' => true, 'labels' => null, 'approved' => false, 'moderationCalls' => 1],
            'approval required, clean text' => ['requireApproval' => true, 'autoModeration' => true, 'labels' => [], 'approved' => false, 'moderationCalls' => 1],
            'approval required, a label' => ['requireApproval' => true, 'autoModeration' => true, 'labels' => ['spam'], 'approved' => false, 'moderationCalls' => 1],
            'auto moderation off' => ['requireApproval' => false, 'autoModeration' => false, 'labels' => ['spam'], 'approved' => true, 'moderationCalls' => 0],
            'auto moderation off, Groq down' => ['requireApproval' => false, 'autoModeration' => false, 'labels' => null, 'approved' => true, 'moderationCalls' => 0],
            'auto moderation off, approval required' => ['requireApproval' => true, 'autoModeration' => false, 'labels' => [], 'approved' => false, 'moderationCalls' => 0],
        ];
    }

    /**
     * @param  list<string>|null  $labels
     */
    #[DataProvider('matrix')]
    public function test_a_new_review_starts_approved_or_pending_by_the_settings(bool $requireApproval, bool $autoModeration, ?array $labels, bool $approved, int $moderationCalls): void
    {
        $this->changeSettings(['reviews_require_approval' => $requireApproval, 'review_auto_moderation' => $autoModeration]);
        $this->groqModeratesAs($labels);
        $author = User::factory()->create();

        $this->as($author)->putJson('/api/reviews/mine', ['tmdb_id' => 550, 'content' => 'Sharp and strange.', 'rating' => 4])
            ->assertOk()
            ->assertJsonPath('review.approved', $approved);

        $review = Review::query()->firstOrFail();
        $this->assertSame($approved, $review->is_approved);
        $this->assertSame($autoModeration ? $labels : null, $review->moderation_labels);
        $this->assertSame($moderationCalls, $this->moderationCalls);
        $this->as(User::factory()->create())->getJson('/api/reviews?tmdb_id=550')->assertJsonCount($approved ? 1 : 0, 'reviews');
    }

    public function test_an_edited_review_goes_back_to_pending_when_approval_is_required(): void
    {
        $this->groqModeratesAs([]);
        $author = User::factory()->create();
        $this->as($author)->putJson('/api/reviews/mine', ['tmdb_id' => 550, 'content' => 'First take.', 'rating' => 4])->assertJsonPath('review.approved', true);
        $this->changeSettings(['reviews_require_approval' => true]);

        $this->as($author)->putJson('/api/reviews/mine', ['tmdb_id' => 550, 'content' => 'Second take.', 'rating' => 4])
            ->assertOk()
            ->assertJsonPath('review.approved', false);

        $this->assertSame(1, Review::count());
        $this->assertFalse(Review::query()->firstOrFail()->is_approved);
    }

    public function test_turning_auto_moderation_off_publishes_an_edit_the_moderation_would_have_held(): void
    {
        $this->groqModeratesAs(['spam']);
        $author = User::factory()->create();
        $this->as($author)->putJson('/api/reviews/mine', ['tmdb_id' => 550, 'content' => 'Buy my stuff.', 'rating' => 1])->assertJsonPath('review.approved', false);
        $this->changeSettings(['review_auto_moderation' => false]);

        $this->as($author)->putJson('/api/reviews/mine', ['tmdb_id' => 550, 'content' => 'Buy my stuff now.', 'rating' => 1])
            ->assertOk()
            ->assertJsonPath('review.approved', true)
            ->assertJsonPath('review.moderation_labels', null);
    }

    public function test_the_authors_own_spoiler_tick_still_counts_without_auto_moderation(): void
    {
        $this->changeSettings(['review_auto_moderation' => false]);
        $this->groqModeratesAs([]);

        $this->as(User::factory()->create())->putJson('/api/reviews/mine', ['tmdb_id' => 550, 'content' => 'He was dead all along.', 'rating' => 5, 'has_spoilers' => true])
            ->assertOk()
            ->assertJsonPath('review.has_spoilers', true)
            ->assertJsonPath('review.approved', true);
    }

    public function test_star_only_ratings_are_unaffected_by_both_settings(): void
    {
        $this->changeSettings(['reviews_require_approval' => true, 'review_auto_moderation' => false]);
        $member = User::factory()->create();

        $this->as($member)->putJson('/api/ratings/550', ['rating' => 4])->assertOk();

        $this->assertTrue(Review::query()->firstOrFail()->is_approved);
        $this->as(User::factory()->create())->getJson('/api/reviews?tmdb_id=550')->assertJsonPath('summary.ratings', 1);
    }

    public function test_no_moderation_request_reaches_groq_while_auto_moderation_is_off(): void
    {
        config(['services.groq.key' => 'test-key', 'services.groq.url' => 'https://api.groq.com/openai/v1', 'services.groq.model' => 'openai/gpt-oss-20b']);
        $this->changeSettings(['review_auto_moderation' => false]);
        Http::preventStrayRequests();
        Http::fake(['api.groq.com/openai/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => json_encode(['sentiment' => 'positive'])]]]])]);

        $this->as(User::factory()->create())->putJson('/api/reviews/mine', ['tmdb_id' => 550, 'content' => 'Full review text.', 'rating' => 4])
            ->assertOk()
            ->assertJsonPath('review.approved', true);

        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request): bool => $request['response_format']['json_schema']['name'] === 'review_moderation');
    }

    public function test_the_moderation_request_is_sent_while_auto_moderation_is_on(): void
    {
        config(['services.groq.key' => 'test-key', 'services.groq.url' => 'https://api.groq.com/openai/v1', 'services.groq.model' => 'openai/gpt-oss-20b']);
        Http::preventStrayRequests();
        Http::fake(['api.groq.com/openai/v1/chat/completions' => fn ($request) => Http::response(['choices' => [['message' => ['content' => json_encode(
            $request['response_format']['json_schema']['name'] === 'review_moderation' ? ['labels' => []] : ['sentiment' => 'positive'],
        )]]]])]);

        $this->as(User::factory()->create())->putJson('/api/reviews/mine', ['tmdb_id' => 550, 'content' => 'Full review text.', 'rating' => 4])->assertOk();

        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request['response_format']['json_schema']['name'] === 'review_moderation');
    }
}
