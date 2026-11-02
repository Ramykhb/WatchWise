<?php

namespace Tests\Feature\MovieRecommendation;

use App\Groq\InterfaceServices\GroqStructuredOutputInterface;
use App\Models\Review;
use App\Models\User;
use App\Movie\DTOs\Responses\MovieDetailsResponse;
use App\Movie\DTOs\Responses\MovieResponse;
use App\Movie\Exceptions\MovieNotFound;
use App\Movie\InterfaceServices\MovieServiceInterface;
use App\Support\Exceptions\TmdbUnavailable;
use Tests\Feature\Social\SocialTestCase;

class FollowingRecommendationTest extends SocialTestCase
{
    private function rating(User $user, int $id, string $type = 'movie', array $attributes = []): Review
    {
        return Review::create([
            'user_id' => $user->id, 'tmdb_id' => $id, 'media_type' => $type,
            'rating' => 5, 'content' => null, 'is_approved' => true, ...$attributes,
        ]);
    }

    private function friend(User $viewer, array $attributes = []): User
    {
        $friend = User::factory()->create($attributes);
        $viewer->following()->attach($friend);

        return $friend;
    }

    private function mockMovies(array $removed = []): mixed
    {
        $movies = $this->mock(MovieServiceInterface::class);
        $movies->shouldReceive('find')->andReturnUsing(function (int $id, string $type) use ($removed): MovieDetailsResponse {
            if (in_array($id, $removed, true)) {
                throw new MovieNotFound($id);
            }
            $movie = new MovieResponse($id, "Title {$type} {$id}", 'Overview', '2020-01-01', 2020, 4, 100, [18], '/poster.jpg', null, $type);

            return new MovieDetailsResponse($movie, [], null, null, null, 8, null, null, null, null, [], [], null, []);
        });
        $this->mock(GroqStructuredOutputInterface::class)->shouldNotReceive('askWithSchema');

        return $movies;
    }

    public function test_authentication_is_required(): void
    {
        $this->getJson('/api/following-recommendations')->assertUnauthorized();
    }

    public function test_no_follows_returns_an_empty_shelf_without_provider_calls(): void
    {
        $viewer = User::factory()->create();
        $this->rating(User::factory()->create(), 1);
        $this->rating($viewer, 2);
        $this->mock(MovieServiceInterface::class)->shouldNotReceive('find');

        $this->as($viewer)->getJson('/api/following-recommendations')->assertOk()->assertExactJson(['movies' => []]);
    }

    public function test_it_ranks_real_five_star_ratings_by_following_count_and_keeps_tv_ids_separate(): void
    {
        $viewer = User::factory()->create();
        $lina = $this->friend($viewer);
        $omar = $this->friend($viewer);
        $this->rating($lina, 42);
        $this->rating($omar, 42);
        $this->rating($lina, 43, attributes: ['rating' => 4]);
        // A real rating saved through the API contributes to the shelf too.
        $this->as($lina)->putJson('/api/ratings/42', ['rating' => 5, 'type' => 'tv'])->assertOk();
        $this->rating($viewer, 99);
        $this->rating(User::factory()->create(), 100);
        $follower = User::factory()->create();
        $follower->following()->attach($viewer);
        $this->rating($follower, 101);
        $this->mockMovies();

        $this->as($viewer)->getJson('/api/following-recommendations')->assertOk()->assertJsonCount(2, 'movies')
            ->assertJsonPath('movies.0.id', 42)->assertJsonPath('movies.0.media_type', 'movie')
            ->assertJsonPath('movies.0.following_count', 2)->assertJsonPath('movies.0.reason', '2 people you follow rated this ★5')
            ->assertJsonPath('movies.1.media_type', 'tv')->assertJsonPath('movies.1.following_count', 1);
    }

    public function test_it_excludes_watched_titles_without_excluding_same_id_in_another_media_type(): void
    {
        $viewer = User::factory()->create();
        $friend = $this->friend($viewer);
        $this->rating($friend, 42);
        $this->rating($friend, 42, 'tv');
        $viewer->watchlists()->create(['tmdb_id' => 42, 'media_type' => 'movie', 'status' => 'watched']);
        $viewer->watchlists()->create(['tmdb_id' => 42, 'media_type' => 'tv', 'status' => 'to_watch']);
        $movies = $this->mockMovies();
        $movies->shouldNotReceive('find')->with(42, 'movie');

        $this->as($viewer)->getJson('/api/following-recommendations')->assertOk()
            ->assertJsonCount(1, 'movies')->assertJsonPath('movies.0.media_type', 'tv');
    }

    public function test_private_hidden_inactive_pending_and_removed_ratings_never_contribute(): void
    {
        $viewer = User::factory()->create();
        $public = $this->friend($viewer);
        $followersOnly = $this->friend($viewer, ['profile_visibility' => 'followers']);
        $private = $this->friend($viewer, ['profile_visibility' => 'private']);
        $inactive = $this->friend($viewer, ['is_active' => false]);
        $hidden = $this->friend($viewer);
        $hidden->preferences()->create(['settings' => ['privacy' => ['ratings' => false]]]);
        foreach ([$public, $followersOnly, $private, $inactive, $hidden] as $friend) {
            $this->rating($friend, 42);
        }
        $this->rating($public, 43, attributes: ['is_approved' => false]);
        $this->rating($public, 44, attributes: ['removed_at' => now()]);
        $movies = $this->mockMovies();
        $movies->shouldNotReceive('find')->with(43, 'movie');
        $movies->shouldNotReceive('find')->with(44, 'movie');

        $this->as($viewer)->getJson('/api/following-recommendations')->assertOk()
            ->assertJsonCount(1, 'movies')->assertJsonPath('movies.0.following_count', 2);
    }

    public function test_it_returns_at_most_six_titles_and_skips_removed_tmdb_entries(): void
    {
        $viewer = User::factory()->create();
        $friend = $this->friend($viewer);
        foreach (range(1, 10) as $id) {
            $this->rating($friend, $id);
        }
        $this->mockMovies(removed: [1]);

        $this->as($viewer)->getJson('/api/following-recommendations')->assertOk()
            ->assertJsonCount(6, 'movies')->assertJsonPath('movies.0.id', 2)->assertJsonPath('movies.5.id', 7);
    }

    public function test_rating_privacy_follows_and_history_changes_take_effect_on_the_next_request(): void
    {
        $viewer = User::factory()->create();
        $friend = $this->friend($viewer);
        $rating = $this->rating($friend, 42, 'tv');
        $this->mockMovies();
        $this->as($viewer)->getJson('/api/following-recommendations')->assertOk()->assertJsonCount(1, 'movies');
        $rating->update(['rating' => 4]);
        $this->getJson('/api/following-recommendations')->assertOk()->assertJsonCount(0, 'movies');
        $rating->update(['rating' => 5]);
        $friend->preferences()->create(['settings' => ['privacy' => ['ratings' => false]]]);
        $this->getJson('/api/following-recommendations')->assertOk()->assertJsonCount(0, 'movies');
        $friend->preferences()->update(['settings' => ['privacy' => ['ratings' => true]]]);
        $this->getJson('/api/following-recommendations')->assertOk()->assertJsonCount(1, 'movies');
        $this->deleteJson('/api/users/'.$friend->username.'/follow')->assertOk();
        $this->getJson('/api/following-recommendations')->assertOk()->assertJsonCount(0, 'movies');
        $this->postJson('/api/users/'.$friend->username.'/follow')->assertOk();
        $this->postJson('/api/watchlist', ['tmdb_id' => 42, 'media_type' => 'tv', 'status' => 'watched'])->assertCreated();
        $this->getJson('/api/following-recommendations')->assertOk()->assertJsonCount(0, 'movies');
    }

    public function test_provider_failure_is_retryable_instead_of_being_cached_as_an_empty_shelf(): void
    {
        $viewer = User::factory()->create();
        $this->rating($this->friend($viewer), 42);
        $this->mock(MovieServiceInterface::class)->shouldReceive('find')->twice()->andThrow(new TmdbUnavailable);

        foreach (range(1, 2) as $_) {
            $this->as($viewer)->getJson('/api/following-recommendations')->assertStatus(502)
                ->assertExactJson(['message' => 'Movie service is temporarily unavailable.']);
        }
    }
}
