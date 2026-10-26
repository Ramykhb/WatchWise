<?php

namespace Tests\Feature\Social;

use App\Models\Review;
use App\Models\User;

class RatingTest extends SocialTestCase
{
    public function test_a_rating_is_saved_and_comes_back(): void
    {
        $user = User::factory()->create();

        $this->as($user)->getJson('/api/ratings/550')->assertOk()->assertJson(['rating' => null]);
        $this->as($user)->putJson('/api/ratings/550', ['rating' => 4])->assertOk()->assertJson(['rating' => 4]);
        $this->as($user)->getJson('/api/ratings/550')->assertJson(['rating' => 4]);

        // Changing it updates the same row.
        $this->as($user)->putJson('/api/ratings/550', ['rating' => 2])->assertOk();
        $this->as($user)->getJson('/api/ratings/550')->assertJson(['rating' => 2]);
        $this->assertSame(1, Review::where('user_id', $user->id)->where('tmdb_id', 550)->count());
    }

    public function test_ratings_are_per_member(): void
    {
        $maya = User::factory()->create();
        $lina = User::factory()->create();

        $this->as($maya)->putJson('/api/ratings/550', ['rating' => 5])->assertOk();
        $this->as($lina)->getJson('/api/ratings/550')->assertJson(['rating' => null]);
    }

    public function test_rating_a_reviewed_film_keeps_the_review(): void
    {
        $user = User::factory()->create();
        Review::create(['user_id' => $user->id, 'tmdb_id' => 550, 'rating' => 3, 'content' => 'Great twist.', 'sentiment' => 'positive', 'is_approved' => false]);

        $this->as($user)->putJson('/api/ratings/550', ['rating' => 5])->assertOk();

        $review = Review::where('user_id', $user->id)->where('tmdb_id', 550)->sole();
        $this->assertSame(5, $review->rating);
        $this->assertSame('Great twist.', $review->content);
        $this->assertFalse($review->is_approved);
    }

    public function test_the_rating_must_be_one_to_five_stars(): void
    {
        $user = User::factory()->create();

        $this->as($user)->putJson('/api/ratings/550', ['rating' => 0])->assertUnprocessable();
        $this->as($user)->putJson('/api/ratings/550', ['rating' => 6])->assertUnprocessable();
        $this->as($user)->putJson('/api/ratings/550', [])->assertUnprocessable();
    }

    public function test_guests_cannot_rate(): void
    {
        $this->getJson('/api/ratings/550')->assertUnauthorized();
        $this->putJson('/api/ratings/550', ['rating' => 4])->assertUnauthorized();
    }

    public function test_star_ratings_feed_top_four_but_are_not_counted_as_reviews(): void
    {
        $user = User::factory()->create();
        $this->as($user)->putJson('/api/ratings/550', ['rating' => 5])->assertOk();

        $this->as($user)->getJson("/api/users/{$user->username}")
            ->assertJsonPath('top4', [550])
            ->assertJsonPath('counts.reviews', 0);
        $this->as($user)->getJson('/api/watchlist')->assertJsonPath('ratings.550', 5);
    }
}
