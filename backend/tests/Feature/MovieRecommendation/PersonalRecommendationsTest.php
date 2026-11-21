<?php

namespace Tests\Feature\MovieRecommendation;

use App\Genre\DTOs\Responses\GenreResponse;
use App\Groq\InterfaceServices\GroqStructuredOutputInterface;
use App\Models\Review;
use App\Models\User;
use App\Movie\DTOs\Responses\MovieDetailsResponse;
use App\Movie\DTOs\Responses\MoviePageResponse;
use App\Movie\DTOs\Responses\MovieResponse;
use App\Movie\InterfaceServices\MovieServiceInterface;
use App\MovieRecommendation\Support\WatchedPeople;
use Tests\Feature\Social\SocialTestCase;

class PersonalRecommendationsTest extends SocialTestCase
{
    private function movie(int $id): MovieResponse
    {
        return new MovieResponse($id, "Title $id", 'Overview', null, null, 4, 50, [18], null, null);
    }

    public function test_high_ratings_drive_the_source_and_disliked_candidates_are_excluded(): void
    {
        $user = User::factory()->create();
        foreach ([42, 43] as $id) {
            $user->watchlists()->create(['tmdb_id' => $id, 'media_type' => 'movie', 'status' => 'watched']);
        }
        foreach ([42 => 1, 43 => 5, 44 => 2] as $id => $rating) {
            Review::create(['user_id' => $user->id, 'tmdb_id' => $id, 'media_type' => 'movie', 'rating' => $rating]);
        }
        $source = new MovieDetailsResponse($this->movie(43), [new GenreResponse(18, 'Drama')], null, null, null, 8, null, null,
            null, null, [], [], null, [$this->movie(44), $this->movie(45)]);
        $movies = $this->mock(MovieServiceInterface::class);
        $movies->shouldReceive('find')->once()->with(43, 'movie')->andReturn($source);
        $movies->shouldReceive('discover')->once()->andReturn(new MoviePageResponse(1, 1, 0, []));
        $this->mock(GroqStructuredOutputInterface::class)->shouldReceive('askWithSchema')->once()
            ->withArgs(fn ($prompt) => str_contains($prompt, '"43":5') && ! str_contains(explode('. TMDB candidates: ', $prompt)[1], 'Title 44'))
            ->andReturn(['movies' => [['id' => 45, 'media_type' => 'movie', 'reason' => 'Fits your taste.']]]);

        $this->as($user)->getJson('/api/watched-recommendations')->assertOk()
            ->assertJsonPath('source.id', 43)->assertJsonPath('movies.0.id', 45);
    }

    public function test_genres_work_without_history_and_preference_and_rating_changes_invalidate_the_cache(): void
    {
        $user = User::factory()->create();
        $user->preferences()->create(['favorite_genres' => ['Drama']]);
        $movies = $this->mock(MovieServiceInterface::class);
        $movies->shouldNotReceive('find');
        $movies->shouldReceive('discover')->times(3)->withArgs(fn ($query) => in_array($query->genres, ['18', '35'], true))
            ->andReturn(new MoviePageResponse(1, 1, 1, [$this->movie(45)]));
        $this->mock(GroqStructuredOutputInterface::class)->shouldReceive('askWithSchema')->times(3)
            ->andReturn(['movies' => [['id' => 45, 'media_type' => 'movie', 'reason' => 'A favorite genre.']]]);
        $this->as($user)->getJson('/api/watched-recommendations')->assertOk()->assertJsonPath('source', null)->assertJsonCount(1, 'movies');
        $this->getJson('/api/watched-recommendations')->assertOk();
        $user->preferences()->update(['favorite_genres' => ['Comedy']]);
        $this->getJson('/api/watched-recommendations')->assertOk();
        Review::create(['user_id' => $user->id, 'tmdb_id' => 99, 'media_type' => 'tv', 'rating' => 5]);
        $this->getJson('/api/watched-recommendations')->assertOk();
    }

    public function test_an_all_disliked_history_does_not_generate_picks(): void
    {
        $user = User::factory()->create();
        $user->watchlists()->create(['tmdb_id' => 42, 'media_type' => 'movie', 'status' => 'watched']);
        Review::create(['user_id' => $user->id, 'tmdb_id' => 42, 'media_type' => 'movie', 'rating' => 1]);
        $this->mock(MovieServiceInterface::class)->shouldNotReceive('find', 'discover', 'credits');
        $this->as($user)->getJson('/api/watched-recommendations')->assertOk()->assertExactJson(['source' => null, 'movies' => []]);
        $this->getJson('/api/people-recommendations')->assertOk()->assertExactJson(['director' => null, 'actor' => null, 'movies' => []]);
    }

    public function test_people_are_weighted_by_personal_ratings_rather_than_frequency_alone(): void
    {
        $credits = [
            '42' => ['cast' => [['id' => 10, 'name' => 'Frequent']]],
            '43' => ['cast' => [['id' => 10, 'name' => 'Frequent']]],
            '44' => ['cast' => [['id' => 20, 'name' => 'Favorite']]],
        ];
        $this->assertSame(20, WatchedPeople::leaders($credits, ['42' => 1, '43' => 1, '44' => 5])['actor']['id']);
    }
}
