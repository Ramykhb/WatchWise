<?php

namespace Tests\Feature\Movie;

use App\Genre\DTOs\Responses\GenreResponse;
use App\Models\LocalMovie;
use App\Models\MovieList;
use App\Models\User;
use App\Movie\DTOs\Responses\MovieDetailsResponse;
use App\Movie\DTOs\Responses\MovieResponse;
use App\Movie\Exceptions\MovieNotFound;
use App\Movie\InterfaceRepository\MovieCatalogRepositoryInterface;
use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use App\Movie\InterfaceServices\MovieServiceInterface;
use App\Movie\Services\MovieCatalogService;
use App\Support\Exceptions\TmdbUnavailable;
use Mockery\MockInterface;
use Tests\Feature\Social\SocialTestCase;

class MovieCatalogTest extends SocialTestCase
{
    private function details(): MovieDetailsResponse
    {
        return new MovieDetailsResponse(
            movie: new MovieResponse(550, 'Fight Club', 'An insomniac...', '1999-10-15', 1999, 8.4, 1000, [], 'https://image.tmdb.org/t/p/w500/abc123.jpg', null),
            genres: [new GenreResponse(18, 'Drama'), new GenreResponse(53, 'Thriller')],
            tagline: null,
            runtime: 139,
            status: 'Released',
            tmdbScore: 8.4,
            originalLanguage: null,
            certification: null,
            budget: null,
            revenue: null,
            directors: [],
            cast: [],
            trailer: null,
            similar: [],
        );
    }

    private function catalogWith(MockInterface $movies): MovieCatalogService
    {
        return new MovieCatalogService(app(MovieCatalogRepositoryInterface::class), $movies);
    }

    private function movies(): MockInterface
    {
        return $this->mock(MovieServiceInterface::class);
    }

    public function test_remember_stores_a_film_the_first_time(): void
    {
        $movies = $this->movies();
        $movies->shouldReceive('find')->once()->with(550)->andReturn($this->details());

        $this->catalogWith($movies)->remember(550);

        $this->assertDatabaseHas('movies', [
            'tmdb_id' => 550,
            'title' => 'Fight Club',
            'poster_path' => '/abc123.jpg',
            'runtime' => 139,
            'is_hidden' => false,
        ]);
        $this->assertSame([18, 53], LocalMovie::where('tmdb_id', 550)->value('genre_ids'));
    }

    public function test_remember_leaves_an_existing_film_alone(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 550, 'title' => 'Edited by an admin']);
        $movies = $this->movies();
        $movies->shouldNotReceive('find');

        $this->catalogWith($movies)->remember(550);

        $this->assertDatabaseHas('movies', ['tmdb_id' => 550, 'title' => 'Edited by an admin']);
    }

    public function test_remember_ignores_a_film_tmdb_cannot_give(): void
    {
        $movies = $this->movies();
        $movies->shouldReceive('find')->with(1)->andThrow(new MovieNotFound(1));
        $movies->shouldReceive('find')->with(2)->andThrow(new TmdbUnavailable);

        $catalog = $this->catalogWith($movies);
        $catalog->remember(1);
        $catalog->remember(2);

        $this->assertDatabaseCount('movies', 0);
    }

    public function test_watchlisting_a_film_remembers_it(): void
    {
        $this->mock(MovieCatalogServiceInterface::class)->shouldReceive('remember')->once()->with(550);

        $this->as(User::factory()->create())->postJson('/api/watchlist', ['tmdb_id' => 550])->assertCreated();
    }

    public function test_rating_a_film_remembers_it(): void
    {
        $this->mock(MovieCatalogServiceInterface::class)->shouldReceive('remember')->once()->with(550);

        $this->as(User::factory()->create())->putJson('/api/ratings/550', ['rating' => 4])->assertOk();
    }

    public function test_listing_a_film_remembers_it(): void
    {
        $user = User::factory()->create();
        $list = MovieList::create(['user_id' => $user->id, 'title' => 'Faves', 'is_public' => false]);
        $this->mock(MovieCatalogServiceInterface::class)->shouldReceive('remember')->once()->with(550);

        $this->as($user)->postJson("/api/lists/{$list->id}/items", ['tmdb_id' => 550])->assertOk();
    }

    public function test_updating_a_list_only_remembers_films_it_just_gained(): void
    {
        $user = User::factory()->create();
        $list = MovieList::create(['user_id' => $user->id, 'title' => 'Faves', 'is_public' => false]);
        $list->items()->create(['tmdb_id' => 550, 'position' => 0]);
        $this->mock(MovieCatalogServiceInterface::class)->shouldReceive('remember')->once()->with(603);

        $this->as($user)->patchJson("/api/lists/{$list->id}", ['tmdb_ids' => [550, 603]])->assertOk();
    }

    public function test_a_bulk_list_save_remembers_only_the_first_few_new_films(): void
    {
        $this->mock(MovieCatalogServiceInterface::class)->shouldReceive('remember')->times(10);

        $this->as(User::factory()->create())
            ->postJson('/api/lists', ['title' => 'Big', 'tmdb_ids' => range(1, 50)])
            ->assertCreated();
    }

    public function test_creating_a_list_remembers_every_film_in_it(): void
    {
        $catalog = $this->mock(MovieCatalogServiceInterface::class);
        $catalog->shouldReceive('remember')->once()->with(550);
        $catalog->shouldReceive('remember')->once()->with(603);

        $this->as(User::factory()->create())
            ->postJson('/api/lists', ['title' => 'Faves', 'tmdb_ids' => [550, 603]])
            ->assertCreated();
    }
}
