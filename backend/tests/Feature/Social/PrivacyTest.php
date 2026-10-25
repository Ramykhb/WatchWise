<?php

namespace Tests\Feature\Social;

use App\ActivityFeed\Services\ActivityFeedService;
use App\Models\MovieNight;
use App\Models\Review;
use App\Models\User;
use App\Models\UserPreference;

class PrivacyTest extends SocialTestCase
{
    private function review(User $user, int $tmdbId, int $rating, bool $approved = true): Review
    {
        $review = Review::create(['user_id' => $user->id, 'tmdb_id' => $tmdbId, 'rating' => $rating, 'content' => 'Good', 'sentiment' => 'positive', 'is_approved' => $approved]);
        app(ActivityFeedService::class)->record($user, 'review', $review, $tmdbId);

        return $review;
    }

    private function hide(User $user, array $sections): void
    {
        UserPreference::updateOrCreate(['user_id' => $user->id], ['settings' => ['privacy' => array_fill_keys($sections, false)]]);
    }

    public function test_restricted_profile_only_shows_name_and_handle(): void
    {
        $viewer = User::factory()->create();
        $private = User::factory()->private()->create(['bio' => 'Secret bio', 'location' => 'Hidden', 'avatar' => 'http://x/a.png']);

        $this->as($viewer)->getJson("/api/users/{$private->username}")
            ->assertOk()
            ->assertJsonPath('restricted', true)
            ->assertJsonPath('user.username', $private->username)
            ->assertJsonPath('user.bio', null)
            ->assertJsonPath('user.location', null)
            ->assertJsonPath('user.avatar', null)
            ->assertJsonPath('user.member_since', null)
            ->assertJsonPath('counts', null)
            ->assertJsonMissingPath('activity');
    }

    public function test_hidden_sections_are_removed_from_the_profile(): void
    {
        $viewer = User::factory()->create();
        $owner = User::factory()->create();
        $fan = User::factory()->create();
        $fan->following()->attach($owner->id);
        $this->review($owner, 1001, 5);
        $this->hide($owner, ['ratings', 'reviews', 'following']);

        $this->as($viewer)->getJson("/api/users/{$owner->username}")
            ->assertOk()
            ->assertJsonPath('top4', [])
            ->assertJsonPath('reviews', [])
            ->assertJsonPath('followers', [])
            ->assertJsonPath('following', [])
            ->assertJsonPath('activity', [])
            ->assertJsonPath('counts.reviews', null)
            ->assertJsonPath('counts.following', null)
            ->assertJsonPath('counts.followers', 1);
        $this->as($viewer)->getJson("/api/users/{$owner->username}/followers")->assertForbidden();

        // The owner still sees everything.
        $this->as($owner)->getJson("/api/users/{$owner->username}")
            ->assertJsonPath('top4', [1001])
            ->assertJsonCount(1, 'reviews')
            ->assertJsonCount(1, 'followers');
    }

    public function test_unapproved_reviews_reach_only_their_author(): void
    {
        $viewer = User::factory()->create();
        $owner = User::factory()->create();
        $this->review($owner, 1003, 4, approved: false);

        $this->as($viewer)->getJson("/api/users/{$owner->username}")
            ->assertJsonPath('reviews', [])
            ->assertJsonPath('activity', [])
            ->assertJsonPath('top4', [])
            ->assertJsonPath('counts.reviews', 0);
        $this->as($owner)->getJson("/api/users/{$owner->username}")
            ->assertJsonCount(1, 'reviews')
            ->assertJsonCount(1, 'activity');
    }

    public function test_home_feed_uses_current_privacy_settings(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $me->following()->attach($friend->id);
        $this->review($friend, 1002, 5);
        $this->as($me)->getJson('/api/home')->assertJsonCount(1, 'activity');

        // Hiding reviews later removes the old activity too.
        $this->hide($friend, ['reviews']);
        $this->as($me)->getJson('/api/home')->assertJsonCount(0, 'activity');

        // So does making the whole profile private.
        UserPreference::where('user_id', $friend->id)->delete();
        $friend->forceFill(['profile_visibility' => 'private'])->save();
        $this->as($me)->getJson('/api/home')->assertJsonCount(0, 'activity');
    }

    public function test_votes_are_rejected_once_voting_is_closed_or_cancelled(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $night = MovieNight::create(['organizer_id' => $host->id, 'title' => 'Friday', 'scheduled_at' => now()->addDays(2)]);
        $night->invites()->create(['user_id' => $guest->id, 'status' => 'accepted']);
        $night->candidates()->create(['tmdb_id' => 1003, 'added_by' => $host->id]);

        $this->as($host)->patchJson("/api/movie-nights/{$night->id}", ['status' => 'confirmed', 'winning_tmdb_id' => 1003])->assertOk();
        $this->as($guest)->putJson("/api/movie-nights/{$night->id}/vote", ['tmdb_id' => 1008])->assertUnprocessable();
        $this->assertSame(1, $night->candidates()->count());

        $this->as($host)->patchJson("/api/movie-nights/{$night->id}", ['status' => 'cancelled', 'winning_tmdb_id' => null])->assertOk();
        $this->as($guest)->putJson("/api/movie-nights/{$night->id}/vote", ['tmdb_id' => 1003])->assertUnprocessable();
    }

    public function test_winner_must_be_on_the_shortlist(): void
    {
        $host = User::factory()->create();
        $night = MovieNight::create(['organizer_id' => $host->id, 'title' => 'Friday', 'scheduled_at' => now()->addDays(2)]);
        $night->candidates()->create(['tmdb_id' => 1003, 'added_by' => $host->id]);

        $this->as($host)->patchJson("/api/movie-nights/{$night->id}", ['status' => 'confirmed', 'winning_tmdb_id' => 9999])
            ->assertUnprocessable()->assertJsonValidationErrors('winning_tmdb_id');
        $this->as($host)->patchJson("/api/movie-nights/{$night->id}", ['winning_tmdb_id' => 1003])->assertOk();
    }

    public function test_generated_usernames_stay_within_twenty_characters(): void
    {
        $name = 'Maximilian Alexander Longname';
        $first = User::factory()->create(['name' => $name]);
        $second = User::factory()->create(['name' => $name]);

        $this->assertSame(20, strlen($first->username));
        $this->assertLessThanOrEqual(User::USERNAME_MAX, strlen($second->username));
        $this->assertNotSame($first->username, $second->username);
        $this->assertStringEndsWith('2', $second->username);
    }
}
