<?php

namespace Tests\Feature\MovieRecommendation;

use App\Genre\DTOs\Responses\GenreResponse;
use App\Groq\InterfaceServices\GroqStructuredOutputInterface;
use App\Models\LocalMovie;
use App\Models\Review;
use App\Models\User;
use App\Movie\DTOs\Responses\MovieDetailsResponse;
use App\Movie\DTOs\Responses\MovieResponse;
use App\Movie\InterfaceServices\MovieServiceInterface;
use App\Person\InterfaceRepository\PersonRepositoryInterface;
use Tests\Concerns\UsesMovieCatalogue;
use Tests\Feature\Social\SocialTestCase;

class HiddenMoviesRecommendationTest extends SocialTestCase
{
    use UsesMovieCatalogue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useRealCatalogue();
    }

    private function movie(int $id, string $type = 'movie'): MovieResponse
    {
        return new MovieResponse($id, "Title {$type} {$id}", 'Overview', '2020-01-01', 2020, 4, 100, [18], '/poster.jpg', null, $type);
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{id: int, media_type: string, reason: string}>
     */
    private function picks(array $ids): array
    {
        return array_map(fn (int $id): array => ['id' => $id, 'media_type' => 'movie', 'reason' => 'A pick.'], $ids);
    }

    public function test_a_hidden_movie_is_left_out_of_following_recommendations(): void
    {
        $viewer = User::factory()->create();
        $friend = User::factory()->create();
        $viewer->following()->attach($friend);
        foreach ([['movie', 42], ['movie', 43], ['tv', 42]] as [$type, $id]) {
            Review::create(['user_id' => $friend->id, 'tmdb_id' => $id, 'media_type' => $type, 'rating' => 5, 'content' => null, 'is_approved' => true]);
        }
        $this->hide(42);
        $movies = $this->mock(MovieServiceInterface::class);
        $movies->shouldReceive('find')->andReturnUsing(fn (int $id, string $type): MovieDetailsResponse => new MovieDetailsResponse(
            $this->movie($id, $type), [], null, null, null, 8, null, null, null, null, [], [], null, [],
        ));
        $movies->shouldNotReceive('find')->with(42, 'movie');

        $this->as($viewer)->getJson('/api/following-recommendations')
            ->assertOk()
            ->assertJsonCount(2, 'movies')
            ->assertJsonPath('movies.0.id', 43)
            ->assertJsonPath('movies.1.id', 42)
            ->assertJsonPath('movies.1.media_type', 'tv');
    }

    public function test_hiding_a_film_removes_it_from_cached_watched_recommendations_at_once(): void
    {
        $user = User::factory()->create();
        $user->watchlists()->create(['tmdb_id' => 42, 'media_type' => 'tv', 'status' => 'watched']);
        $related = array_map(fn (int $id): MovieResponse => $this->movie($id), range(301, 307));
        $source = new MovieDetailsResponse(
            $this->movie(42, 'tv'), [new GenreResponse(18, 'Drama')], null, null, null, 8, null, null, null, null, [], [], null, $related,
        );
        $this->mock(MovieServiceInterface::class)->shouldReceive('find')->once()->with(42, 'tv')->andReturn($source);
        $this->mock(GroqStructuredOutputInterface::class)->shouldReceive('askWithSchema')->once()
            ->andReturn(['movies' => $this->picks(range(301, 306))]);

        $this->as($user)->getJson('/api/watched-recommendations')->assertOk()->assertJsonCount(6, 'movies');

        $this->hide(301);

        $this->as($user)->getJson('/api/watched-recommendations')
            ->assertOk()
            ->assertJsonCount(5, 'movies')
            ->assertJsonPath('movies.0.id', 302)
            ->assertJsonPath('source.id', 42);
    }

    public function test_hiding_a_film_removes_it_from_cached_favourite_people_recommendations_at_once(): void
    {
        $user = User::factory()->create();
        foreach ([42, 43] as $id) {
            $user->watchlists()->create(['tmdb_id' => $id, 'media_type' => 'movie', 'status' => 'watched']);
        }
        $this->mock(MovieServiceInterface::class)->shouldReceive('credits')->once()->andReturn([
            '42' => ['cast' => [['id' => 10, 'name' => 'Top Actor']], 'crew' => [['id' => 20, 'name' => 'Top Director', 'job' => 'Director']]],
            '43' => ['cast' => [['id' => 10, 'name' => 'Top Actor']], 'crew' => [['id' => 20, 'name' => 'Top Director', 'job' => 'Director']]],
        ]);
        $film = fn (int $id): array => ['id' => $id, 'title' => "Film {$id}", 'release_date' => '2020-01-01', 'vote_count' => 100, 'vote_average' => 8, 'genre_ids' => [18], 'poster_path' => '/poster.jpg', 'overview' => 'Drama.'];
        $people = $this->mock(PersonRepositoryInterface::class);
        $people->shouldReceive('find')->once()->with(20)->andReturn(['movie_credits' => ['crew' => array_map(fn (int $id): array => $film($id) + ['job' => 'Director'], [1, 2, 3])]]);
        $people->shouldReceive('find')->once()->with(10)->andReturn(['movie_credits' => ['cast' => array_map($film, [4, 5, 6])]]);
        $this->mock(GroqStructuredOutputInterface::class)->shouldReceive('askWithSchema')->once()
            ->andReturn(['movies' => $this->picks(range(1, 6))]);

        $this->as($user)->getJson('/api/people-recommendations')->assertOk()->assertJsonCount(6, 'movies');

        LocalMovie::factory()->hidden()->create(['tmdb_id' => 1]);

        $this->as($user)->getJson('/api/people-recommendations')
            ->assertOk()
            ->assertJsonCount(5, 'movies')
            ->assertJsonPath('movies.0.id', 2);
    }
}
