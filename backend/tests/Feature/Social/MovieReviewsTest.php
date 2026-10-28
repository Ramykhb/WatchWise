<?php

namespace Tests\Feature\Social;

use App\Groq\Exceptions\GroqUnavailable;
use App\Groq\InterfaceServices\GroqStructuredOutputInterface;
use App\Models\Review;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Support\Facades\Http;
use Mockery;

class MovieReviewsTest extends SocialTestCase
{
    private ?string $groqSentiment = null;

    private mixed $groqLabels = [];

    /** Groq answers with this sentiment (null = Groq is down). */
    private function groqSays(?string $sentiment, mixed $labels = []): void
    {
        $this->groqSentiment = $sentiment;
        $this->groqLabels = $labels;
        $this->app->instance(GroqStructuredOutputInterface::class, Mockery::mock(GroqStructuredOutputInterface::class, function ($mock) {
            $call = $mock->shouldReceive('askWithSchema');
            $call->andReturnUsing(function ($prompt, $name) {
                if ($this->groqSentiment === null) {
                    throw new GroqUnavailable('down');
                }

                return $name === 'review_moderation' ? ['labels' => $this->groqLabels] : ['sentiment' => $this->groqSentiment];
            });
        }));
    }

    private function approvedReview(User $author, int $rating, string $sentiment, array $attributes = []): Review
    {
        return Review::create([
            'user_id' => $author->id, 'tmdb_id' => 550, 'media_type' => 'movie', 'rating' => $rating,
            'content' => "Review by {$author->name}", 'sentiment' => $sentiment, 'is_approved' => true, ...$attributes,
        ]);
    }

    public function test_writing_a_review_sets_your_stars_and_waits_for_approval(): void
    {
        $this->groqSays('neutral');
        $me = User::factory()->create();

        $this->as($me)->putJson('/api/reviews/mine', ['tmdb_id' => 550, 'content' => 'Sharp and strange.', 'rating' => 4, 'has_spoilers' => true])
            ->assertOk()
            ->assertJsonPath('review.sentiment', 'neutral')
            ->assertJsonPath('review.has_spoilers', true)
            ->assertJsonPath('review.approved', false);

        // It is your rating too, and it shows as yours while it waits.
        $this->as($me)->getJson('/api/ratings/550')->assertJson(['rating' => 4]);
        $this->as($me)->getJson('/api/reviews?tmdb_id=550')
            ->assertOk()
            ->assertJsonPath('mine.content', 'Sharp and strange.')
            ->assertJsonCount(0, 'reviews');

        // Others don't see it until an administrator approves it.
        $this->as(User::factory()->create())->getJson('/api/reviews?tmdb_id=550')->assertJsonCount(0, 'reviews');
    }

    public function test_editing_keeps_one_review_and_deleting_keeps_the_stars(): void
    {
        $this->groqSays('positive');
        $me = User::factory()->create();

        $this->as($me)->putJson('/api/reviews/mine', ['tmdb_id' => 550, 'content' => 'First take.', 'rating' => 3])->assertOk();
        $this->as($me)->putJson('/api/reviews/mine', ['tmdb_id' => 550, 'content' => 'Second take.', 'rating' => 5])->assertOk();
        $this->assertSame(1, Review::where('user_id', $me->id)->count());

        $this->as($me)->deleteJson('/api/reviews/mine?tmdb_id=550')->assertOk();
        $this->as($me)->getJson('/api/reviews?tmdb_id=550')->assertJsonPath('mine', null);
        $this->as($me)->getJson('/api/ratings/550')->assertJson(['rating' => 5]);
    }

    public function test_sentiment_falls_back_to_the_stars_when_groq_is_down(): void
    {
        $this->groqSays(null);
        $me = User::factory()->create();

        $this->as($me)->putJson('/api/reviews/mine', ['tmdb_id' => 550, 'content' => 'Not for me.', 'rating' => 2])
            ->assertOk()
            ->assertJsonPath('review.sentiment', 'negative')
            ->assertJsonPath('review.approved', false);
    }

    public function test_clean_reviews_publish_immediately_and_appear_to_other_members(): void
    {
        $this->groqSays('negative');
        $author = User::factory()->create();
        $this->as($author)->putJson('/api/reviews/mine', [
            'tmdb_id' => 550, 'content' => 'Boring pacing, not for me.', 'rating' => 2,
            'is_approved' => false, 'moderation_labels' => ['spam'],
        ])->assertOk()->assertJsonPath('review.approved', true);
        $this->assertSame([], Review::first()->moderation_labels);
        $this->as(User::factory()->create())->getJson('/api/reviews?tmdb_id=550')
            ->assertJsonCount(1, 'reviews')->assertJsonPath('summary.reviews', 1);
    }

    public function test_each_groq_label_holds_the_review_until_admin_approval(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['offensive language', 'spoiler', 'spam'] as $label) {
            $this->groqSays('neutral', [$label]);
            $response = $this->as($author)->putJson('/api/reviews/mine', [
                'tmdb_id' => 550, 'content' => 'Text for moderation.', 'rating' => 3,
                'is_approved' => true, 'moderation_labels' => [],
            ])->assertOk()->assertJsonPath('review.approved', false)
                ->assertJsonPath('review.moderation_labels', [$label])
                ->assertJsonPath('review.has_spoilers', $label === 'spoiler');
            $id = $response->json('review.id');
            $this->as($viewer)->getJson('/api/reviews?tmdb_id=550')->assertJsonCount(0, 'reviews');
            $this->as($admin)->getJson('/api/admin/reviews')
                ->assertJsonPath('data.0.moderation_labels', [$label]);
            $this->as($admin)->patchJson("/api/admin/reviews/{$id}", ['status' => 'approved'])->assertOk();
            $this->as($viewer)->getJson('/api/reviews?tmdb_id=550')->assertJsonCount(1, 'reviews');
        }
    }

    public function test_an_edit_is_reclassified_and_can_be_declined(): void
    {
        $author = User::factory()->create();
        $review = $this->approvedReview($author, 4, 'positive');
        $this->groqSays('neutral', ['offensive language', 'spam']);
        $this->as($author)->putJson('/api/reviews/mine', [
            'tmdb_id' => 550, 'content' => 'Changed text.', 'rating' => 3,
        ])->assertOk()->assertJsonPath('review.approved', false);
        $this->assertSame(['offensive language', 'spam'], $review->fresh()->moderation_labels);
        $this->as(User::factory()->create(['role' => 'admin']))
            ->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'rejected'])->assertOk();
        $this->assertNull($review->fresh()->content);
        $this->assertNull($review->fresh()->moderation_labels);
        $this->groqSays('positive');
        $this->as($author)->putJson('/api/reviews/mine', [
            'tmdb_id' => 550, 'content' => 'A thoughtful film.', 'rating' => 4,
        ])->assertOk()->assertJsonPath('review.approved', true);
    }

    public function test_invalid_classifier_results_never_auto_approve(): void
    {
        $author = User::factory()->create();
        foreach ([null, 'spam', ['unknown'], [false], ['flag' => 'spam']] as $labels) {
            $this->groqSays('neutral', $labels);
            $this->as($author)->putJson('/api/reviews/mine', [
                'tmdb_id' => 550, 'content' => 'Review text.', 'rating' => 3,
            ])->assertOk()->assertJsonPath('review.approved', false);
            $this->assertNull(Review::first()->moderation_labels);
        }
    }

    public function test_review_text_is_sent_to_groq_with_a_strict_label_schema(): void
    {
        config(['services.groq.key' => 'test-key', 'services.groq.url' => 'https://api.groq.com/openai/v1', 'services.groq.model' => 'openai/gpt-oss-20b']);
        Http::preventStrayRequests();
        Http::fake([
            'api.groq.com/openai/v1/chat/completions' => function ($request) {
                $moderation = $request['response_format']['json_schema']['name'] === 'review_moderation';
                if ($moderation) {
                    $this->assertStringContainsString('Full review text.', $request['messages'][0]['content']);
                    $this->assertSame(['offensive language', 'spoiler', 'spam'], $request['response_format']['json_schema']['schema']['properties']['labels']['items']['enum']);
                    $this->assertTrue($request['response_format']['json_schema']['strict']);
                }

                return Http::response(['choices' => [['message' => [
                    'content' => json_encode($moderation ? ['labels' => []] : ['sentiment' => 'positive']),
                ]]]]);
            },
        ]);
        $this->as(User::factory()->create())->putJson('/api/reviews/mine', [
            'tmdb_id' => 550, 'type' => 'tv', 'content' => 'Full review text.', 'rating' => 4,
        ])->assertOk()->assertJsonPath('review.approved', true);
        Http::assertSentCount(2);
    }

    public function test_the_summary_counts_approved_ratings_and_reviews(): void
    {
        $viewer = User::factory()->create();
        $this->approvedReview(User::factory()->create(), 5, 'positive');
        $this->approvedReview(User::factory()->create(), 4, 'positive');
        $this->approvedReview(User::factory()->create(), 1, 'negative');
        // A star rating without text counts for the average, not for sentiment.
        Review::create(['user_id' => User::factory()->create()->id, 'tmdb_id' => 550, 'media_type' => 'movie', 'rating' => 2, 'is_approved' => true]);
        // Unapproved text and the TV show with the same id don't count.
        $this->approvedReview(User::factory()->create(), 1, 'negative', ['is_approved' => false]);
        $this->approvedReview(User::factory()->create(), 1, 'negative', ['media_type' => 'tv']);

        $this->as($viewer)->getJson('/api/reviews?tmdb_id=550')
            ->assertOk()
            ->assertJsonPath('summary.average', 3)
            ->assertJsonPath('summary.ratings', 4)
            ->assertJsonPath('summary.reviews', 3)
            ->assertJsonPath('summary.distribution', [25, 25, 0, 25, 25])
            ->assertJsonPath('summary.sentiment', ['positive' => 67, 'neutral' => 0, 'negative' => 33])
            ->assertJsonCount(3, 'reviews');
    }

    public function test_helpful_votes(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $review = $this->approvedReview($author, 5, 'positive');

        $this->as($viewer)->postJson("/api/reviews/{$review->id}/helpful")->assertOk()->assertJson(['helpful' => 1]);
        $this->as($viewer)->postJson("/api/reviews/{$review->id}/helpful")->assertOk()->assertJson(['helpful' => 1]);
        $this->as($viewer)->getJson('/api/reviews?tmdb_id=550')
            ->assertJsonPath('reviews.0.helpful', 1)
            ->assertJsonPath('reviews.0.marked_helpful', true);
        $this->as($viewer)->deleteJson("/api/reviews/{$review->id}/helpful")->assertOk()->assertJson(['helpful' => 0]);

        // Not your own review, and not one waiting for approval.
        $this->as($author)->postJson("/api/reviews/{$review->id}/helpful")->assertUnprocessable();
        $pending = $this->approvedReview(User::factory()->create(), 3, 'neutral', ['tmdb_id' => 13, 'is_approved' => false]);
        $this->as($viewer)->postJson("/api/reviews/{$pending->id}/helpful")->assertUnprocessable();
    }

    public function test_reviews_the_viewer_cannot_see_cannot_be_voted_on(): void
    {
        $viewer = User::factory()->create();
        $private = $this->approvedReview(User::factory()->private()->create(), 5, 'positive');
        $quiet = User::factory()->create();
        UserPreference::create(['user_id' => $quiet->id, 'settings' => ['privacy' => ['reviews' => false]]]);
        $hidden = $this->approvedReview($quiet, 5, 'positive', ['tmdb_id' => 13]);
        $friendsOnly = User::factory()->create(['profile_visibility' => 'followers']);
        $forFollowers = $this->approvedReview($friendsOnly, 5, 'positive', ['tmdb_id' => 14]);

        // Knowing the id isn't enough; the answer is the same as for an unapproved review.
        $this->as($viewer)->postJson("/api/reviews/{$private->id}/helpful")->assertUnprocessable()->assertJsonPath('errors.review.0', 'This review can’t be voted on.');
        $this->as($viewer)->postJson("/api/reviews/{$hidden->id}/helpful")->assertUnprocessable();
        $this->as($viewer)->postJson("/api/reviews/{$forFollowers->id}/helpful")->assertUnprocessable();
        $this->assertSame(0, $private->votes()->count() + $hidden->votes()->count() + $forFollowers->votes()->count());

        // Following a followers-only author makes their reviews votable.
        $viewer->following()->attach($friendsOnly->id);
        $this->as($viewer)->postJson("/api/reviews/{$forFollowers->id}/helpful")->assertOk()->assertJson(['helpful' => 1]);
    }

    public function test_resubmitting_a_review_clears_its_helpful_votes(): void
    {
        $this->groqSays('positive');
        $author = User::factory()->create();
        $review = $this->approvedReview($author, 5, 'positive');
        foreach (User::factory()->count(3)->create() as $voter) {
            $this->as($voter)->postJson("/api/reviews/{$review->id}/helpful")->assertOk();
        }
        $this->assertSame(3, $review->votes()->count());

        $this->as($author)->putJson('/api/reviews/mine', ['tmdb_id' => 550, 'content' => 'Completely different take.', 'rating' => 2])
            ->assertOk()
            ->assertJsonPath('review.helpful', 0)
            ->assertJsonPath('review.approved', true);
        $this->assertSame(0, $review->votes()->count());
    }

    public function test_reviews_from_private_authors_stay_hidden(): void
    {
        $viewer = User::factory()->create();
        $this->approvedReview(User::factory()->create(['name' => 'Open']), 5, 'positive');
        $this->approvedReview(User::factory()->private()->create(), 5, 'positive');
        $quiet = User::factory()->create();
        UserPreference::create(['user_id' => $quiet->id, 'settings' => ['privacy' => ['reviews' => false]]]);
        $this->approvedReview($quiet, 5, 'positive');
        $friendsOnly = User::factory()->create(['profile_visibility' => 'followers']);
        $this->approvedReview($friendsOnly, 5, 'positive');

        $this->as($viewer)->getJson('/api/reviews?tmdb_id=550')->assertJsonCount(1, 'reviews')->assertJsonPath('reviews.0.author.name', 'Open');

        $viewer->following()->attach($friendsOnly->id);
        $this->as($viewer)->getJson('/api/reviews?tmdb_id=550')->assertJsonCount(2, 'reviews');
    }

    public function test_sorting_and_pages(): void
    {
        $viewer = User::factory()->create();
        foreach (range(1, 11) as $n) {
            $this->approvedReview(User::factory()->create(), ($n % 5) + 1, 'positive');
        }

        $this->as($viewer)->getJson('/api/reviews?tmdb_id=550&sort=highest')
            ->assertJsonCount(10, 'reviews')
            ->assertJsonPath('reviews.0.rating', 5)
            ->assertJsonPath('has_more', true);
        $this->as($viewer)->getJson('/api/reviews?tmdb_id=550&sort=lowest&page=2')
            ->assertJsonCount(1, 'reviews')
            ->assertJsonPath('has_more', false);
        $this->as($viewer)->getJson('/api/reviews?tmdb_id=550&sort=random')->assertUnprocessable();
    }

    public function test_validation(): void
    {
        $me = User::factory()->create();

        $this->as($me)->putJson('/api/reviews/mine', ['tmdb_id' => 550, 'content' => '   ', 'rating' => 4])->assertUnprocessable();
        $this->as($me)->putJson('/api/reviews/mine', ['tmdb_id' => 550, 'content' => 'Fine', 'rating' => 6])->assertUnprocessable();
        $this->as($me)->putJson('/api/reviews/mine', ['tmdb_id' => 550, 'content' => str_repeat('a', 2001), 'rating' => 4])->assertUnprocessable();
        $this->as($me)->getJson('/api/reviews')->assertUnprocessable();
    }
}
