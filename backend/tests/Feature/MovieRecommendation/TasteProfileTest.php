<?php

namespace Tests\Feature\MovieRecommendation;

use App\Groq\InterfaceServices\GroqStructuredOutputInterface;
use App\Models\Review;
use App\Models\User;
use App\Movie\InterfaceServices\MovieServiceInterface;
use Tests\Feature\Social\SocialTestCase;

class TasteProfileTest extends SocialTestCase
{
    public function test_authentication_is_required(): void
    {
        $this->getJson('/api/taste-profile')->assertUnauthorized();
    }

    public function test_empty_history_has_real_zero_counts_and_no_demo_people_or_genres(): void
    {
        $this->mock(MovieServiceInterface::class)->shouldNotReceive('metadata');
        $this->as(User::factory()->create())->getJson('/api/taste-profile')->assertOk()->assertExactJson([
            'watched_movies' => 0, 'watched_tv' => 0, 'five_star_ratings' => 0, 'following_count' => 0,
            'genres' => [], 'director' => null, 'actor' => null,
        ]);
    }

    public function test_profile_uses_only_own_watched_titles_ratings_and_follows(): void
    {
        $user = User::factory()->create();
        foreach ([[42, 'movie'], [43, 'movie'], [42, 'tv'], [44, 'tv']] as [$id, $type]) {
            $user->watchlists()->create(['tmdb_id' => $id, 'media_type' => $type, 'status' => 'watched']);
        }
        $user->watchlists()->create(['tmdb_id' => 100, 'media_type' => 'movie', 'status' => 'to_watch']);
        $other = User::factory()->create();
        $other->watchlists()->create(['tmdb_id' => 999, 'media_type' => 'movie', 'status' => 'watched']);
        foreach ([[$user, 1, 5], [$user, 2, 5], [$user, 3, 4], [$other, 4, 5]] as [$author, $id, $rating]) {
            Review::create(['user_id' => $author->id, 'tmdb_id' => $id, 'media_type' => 'movie', 'rating' => $rating, 'is_approved' => true]);
        }
        $user->following()->attach($other);
        $inactive = User::factory()->create(['is_active' => false]);
        $user->following()->attach($inactive);
        $cast = [['id' => 99, 'name' => 'Real Actor'], ['id' => 99, 'name' => 'Real Actor']];
        $crew = [['id' => 10, 'name' => 'Real Director', 'job' => 'Director']];
        $this->mock(MovieServiceInterface::class)->shouldReceive('metadata')->once()
            ->withArgs(function (array $titles): bool {
                $this->assertEqualsCanonicalizing([
                    ['id' => 42, 'media_type' => 'movie'], ['id' => 43, 'media_type' => 'movie'],
                    ['id' => 42, 'media_type' => 'tv'], ['id' => 44, 'media_type' => 'tv'],
                ], $titles);

                return true;
            })->andReturn([
                '42' => ['genres' => [['id' => 18, 'name' => 'Drama'], ['id' => 878, 'name' => 'Science Fiction']], 'credits' => ['cast' => $cast, 'crew' => $crew]],
                '43' => ['genres' => [['id' => 18, 'name' => 'Drama'], ['id' => 53, 'name' => 'Thriller']], 'credits' => ['cast' => $cast, 'crew' => $crew]],
                'tv-42' => ['genres' => [['id' => 18, 'name' => 'Drama'], ['id' => 10765, 'name' => 'Sci-Fi & Fantasy']], 'credits' => ['cast' => $cast, 'crew' => []]],
                // Removed title 44 contributes to the watched count, but cannot supply TMDB metadata.
            ]);
        $this->mock(GroqStructuredOutputInterface::class)->shouldNotReceive('askWithSchema');

        $this->as($user)->getJson('/api/taste-profile')->assertOk()
            ->assertJsonPath('watched_movies', 2)->assertJsonPath('watched_tv', 2)
            ->assertJsonPath('five_star_ratings', 2)->assertJsonPath('following_count', 1)
            ->assertJsonCount(3, 'genres')->assertJsonPath('genres.0.id', 18)
            ->assertJsonPath('genres.0.percentage', 50)->assertJsonPath('genres.0.media_type', 'all')
            ->assertJsonPath('genres.1.id', 53)->assertJsonPath('genres.1.percentage', 17)
            ->assertJsonPath('genres.1.media_type', 'movie')
            ->assertJsonPath('director.id', 10)->assertJsonPath('director.watched_count', 2)
            ->assertJsonPath('actor.id', 99)->assertJsonPath('actor.watched_count', 3);
    }

    public function test_tv_genres_remain_distinct_and_stats_refresh_after_history_is_cleared(): void
    {
        $user = User::factory()->create();
        $entry = $user->watchlists()->create(['tmdb_id' => 42, 'media_type' => 'tv', 'status' => 'watched']);
        $this->mock(MovieServiceInterface::class)->shouldReceive('metadata')->once()->andReturn([
            'tv-42' => ['genres' => [['id' => 10765, 'name' => 'Sci-Fi & Fantasy']], 'credits' => ['cast' => [], 'crew' => []]],
        ]);
        $this->as($user)->getJson('/api/taste-profile')->assertOk()->assertJsonPath('genres.0.id', 10765)
            ->assertJsonPath('genres.0.media_type', 'tv')->assertJsonPath('genres.0.percentage', 100)
            ->assertJsonPath('director', null)->assertJsonPath('actor', null);
        $entry->update(['status' => 'to_watch']);
        $this->getJson('/api/taste-profile')->assertOk()->assertJsonCount(0, 'genres')->assertJsonPath('watched_tv', 0);
    }
}
