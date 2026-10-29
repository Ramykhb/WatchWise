<?php

namespace Tests\Feature\Social;

use App\Models\MovieList;
use App\Models\User;
use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** TV shows and movies share TMDB ids (1399 is both), so every saved title keeps its type. */
class TvShowsTest extends SocialTestCase
{
    public function test_a_show_and_a_movie_with_the_same_id_are_rated_separately(): void
    {
        $user = User::factory()->create();

        $this->as($user)->putJson('/api/ratings/1399', ['rating' => 5, 'type' => 'tv'])->assertOk()->assertJson(['rating' => 5]);
        $this->as($user)->putJson('/api/ratings/1399', ['rating' => 2])->assertOk();

        $this->as($user)->getJson('/api/ratings/1399?type=tv')->assertJson(['rating' => 5]);
        $this->as($user)->getJson('/api/ratings/1399')->assertJson(['rating' => 2]);
        $this->as($user)->getJson('/api/ratings/1399?type=movie')->assertJson(['rating' => 2]);
    }

    public function test_the_type_must_be_movie_or_tv(): void
    {
        $user = User::factory()->create();

        $this->as($user)->putJson('/api/ratings/1399', ['rating' => 4, 'type' => 'game'])->assertUnprocessable();
        $this->as($user)->getJson('/api/ratings/1399?type=game')->assertUnprocessable();
        $this->as($user)->postJson('/api/watchlist', ['tmdb_id' => 1399, 'media_type' => 'game'])->assertUnprocessable();
    }

    public function test_a_show_and_a_movie_with_the_same_id_each_get_a_watchlist_row(): void
    {
        $user = User::factory()->create();

        $this->as($user)->postJson('/api/watchlist', ['tmdb_id' => 1399, 'media_type' => 'tv'])
            ->assertCreated()
            ->assertJsonPath('item.media_type', 'tv');
        $this->as($user)->postJson('/api/watchlist', ['tmdb_id' => 1399])
            ->assertCreated()
            ->assertJsonPath('item.media_type', 'movie');
        // Adding the show again is the same row.
        $this->as($user)->postJson('/api/watchlist', ['tmdb_id' => 1399, 'media_type' => 'tv'])->assertOk();

        $this->as($user)->getJson('/api/watchlist')->assertJsonCount(2, 'items');
    }

    public function test_watchlist_ratings_are_keyed_by_title(): void
    {
        $user = User::factory()->create();
        $this->as($user)->putJson('/api/ratings/1399', ['rating' => 5, 'type' => 'tv'])->assertOk();
        $this->as($user)->putJson('/api/ratings/550', ['rating' => 3])->assertOk();

        $this->as($user)->getJson('/api/watchlist')
            ->assertJsonPath('ratings.tv-1399', 5)
            ->assertJsonPath('ratings.550', 3);
    }

    public function test_rolling_back_the_type_keeps_movies_and_drops_shows(): void
    {
        $user = User::factory()->create();
        // The same id saved as both a movie and a show: the old (user_id, tmdb_id) index allows only one.
        foreach (['movie', 'tv'] as $type) {
            DB::table('reviews')->insert(['user_id' => $user->id, 'tmdb_id' => 1399, 'media_type' => $type, 'rating' => 3, 'is_approved' => true]);
            DB::table('watchlists')->insert(['user_id' => $user->id, 'tmdb_id' => 1399, 'media_type' => $type, 'status' => 'to_watch']);
        }
        $migration = require database_path('migrations/2026_10_27_000002_add_media_type_to_reviews_and_watchlists.php');

        $migration->down();

        $this->assertFalse(Schema::hasColumn('reviews', 'media_type'));
        $this->assertSame(1, DB::table('reviews')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('watchlists')->where('user_id', $user->id)->count());

        // Back to the current schema; the kept rows are movies.
        $migration->up();
        $this->assertSame('movie', DB::table('reviews')->where('user_id', $user->id)->value('media_type'));
    }

    public function test_a_list_holds_a_show_and_a_movie_with_the_same_id(): void
    {
        $user = User::factory()->create();

        $this->as($user)->postJson('/api/lists', ['title' => 'Mixed', 'tmdb_ids' => [1399, 'tv-1399', 550]])
            ->assertCreated()
            ->assertJsonPath('list.films', 3)
            ->assertJsonPath('list.items.0.media_type', 'movie')
            ->assertJsonPath('list.items.1.tmdb_id', 1399)
            ->assertJsonPath('list.items.1.media_type', 'tv')
            ->assertJsonPath('list.items.2.tmdb_id', 550)
            ->assertJsonPath('list.cover.1', ['tmdb_id' => 1399, 'media_type' => 'tv']);
    }

    public function test_adding_and_removing_a_show_leaves_the_movie_with_the_same_id(): void
    {
        $user = User::factory()->create();
        $list = MovieList::create(['user_id' => $user->id, 'title' => 'Mixed', 'is_public' => true]);
        $list->items()->create(['tmdb_id' => 1399, 'position' => 0]);

        $this->as($user)->postJson("/api/lists/{$list->id}/items", ['tmdb_id' => 1399, 'media_type' => 'tv'])
            ->assertOk()
            ->assertJsonPath('list.films', 2)
            ->assertJsonPath('list.items.1.media_type', 'tv');
        // Adding the show again changes nothing.
        $this->as($user)->postJson("/api/lists/{$list->id}/items", ['tmdb_id' => 1399, 'media_type' => 'tv'])
            ->assertJsonPath('list.films', 2);

        $this->as($user)->deleteJson("/api/lists/{$list->id}/items/1399?type=tv")
            ->assertOk()
            ->assertJsonPath('list.films', 1)
            ->assertJsonPath('list.items.0.media_type', 'movie');
    }

    public function test_replacing_the_films_keeps_shows_and_movies_apart(): void
    {
        $user = User::factory()->create();
        $list = MovieList::create(['user_id' => $user->id, 'title' => 'Mixed', 'is_public' => true]);
        foreach (['movie', 'tv'] as $position => $type) {
            $list->items()->create(['tmdb_id' => 1399, 'media_type' => $type, 'position' => $position]);
        }

        // The show moves first and the movie with the same id leaves.
        $this->as($user)->patchJson("/api/lists/{$list->id}", ['tmdb_ids' => ['tv-1399', 550]])
            ->assertOk()
            ->assertJsonPath('list.films', 2)
            ->assertJsonPath('list.items.0.tmdb_id', 1399)
            ->assertJsonPath('list.items.0.media_type', 'tv')
            ->assertJsonPath('list.items.1.tmdb_id', 550);
    }

    public function test_lists_say_whether_they_contain_a_show(): void
    {
        $user = User::factory()->create();
        $list = MovieList::create(['user_id' => $user->id, 'title' => 'Shows', 'is_public' => true]);
        $list->items()->create(['tmdb_id' => 1399, 'media_type' => 'tv', 'position' => 0]);

        $this->as($user)->getJson('/api/lists?scope=mine&contains=1399&contains_type=tv')->assertJsonPath('lists.0.has_movie', true);
        // The movie 1399 is a different title.
        $this->as($user)->getJson('/api/lists?scope=mine&contains=1399')->assertJsonPath('lists.0.has_movie', false);
    }

    public function test_list_titles_must_be_movie_ids_or_tv_keys(): void
    {
        $user = User::factory()->create();
        $list = MovieList::create(['user_id' => $user->id, 'title' => 'Mixed', 'is_public' => true]);

        $this->as($user)->postJson('/api/lists', ['title' => 'Bad', 'tmdb_ids' => ['game-1']])->assertUnprocessable();
        $this->as($user)->postJson('/api/lists', ['title' => 'Bad', 'tmdb_ids' => ['tv-0']])->assertUnprocessable();
        $this->as($user)->patchJson("/api/lists/{$list->id}", ['tmdb_ids' => ['tv-1399', 'tv-1399']])->assertUnprocessable();
        $this->as($user)->postJson("/api/lists/{$list->id}/items", ['tmdb_id' => 1399, 'media_type' => 'game'])->assertUnprocessable();
        $this->as($user)->deleteJson("/api/lists/{$list->id}/items/1399?type=game")->assertUnprocessable();
        $this->as($user)->getJson('/api/lists?contains=1399&contains_type=game')->assertUnprocessable();
    }

    public function test_only_movies_in_lists_are_added_to_the_movie_catalogue(): void
    {
        $catalog = $this->mock(MovieCatalogServiceInterface::class);
        $catalog->shouldReceive('remember')->once()->with(550);

        $this->as(User::factory()->create())
            ->postJson('/api/lists', ['title' => 'Mixed', 'tmdb_ids' => [550, 'tv-1399']])
            ->assertCreated();
    }

    public function test_rolling_back_list_types_keeps_movies_and_drops_shows(): void
    {
        $user = User::factory()->create();
        $list = MovieList::create(['user_id' => $user->id, 'title' => 'Mixed', 'is_public' => true]);
        foreach (['movie', 'tv'] as $position => $type) {
            $list->items()->create(['tmdb_id' => 1399, 'media_type' => $type, 'position' => $position]);
        }
        $migration = require database_path('migrations/2026_10_28_154529_add_media_type_to_movie_list_items.php');

        $migration->down();

        $this->assertFalse(Schema::hasColumn('movie_list_items', 'media_type'));
        $this->assertSame(1, DB::table('movie_list_items')->where('movie_list_id', $list->id)->count());

        // Back to the current schema; the kept item is a movie.
        $migration->up();
        $this->assertSame('movie', DB::table('movie_list_items')->where('movie_list_id', $list->id)->value('media_type'));
    }

    public function test_profile_top_four_and_watched_only_list_movies(): void
    {
        $user = User::factory()->create();
        $this->as($user)->putJson('/api/ratings/1399', ['rating' => 5, 'type' => 'tv'])->assertOk();
        $this->as($user)->putJson('/api/ratings/550', ['rating' => 4])->assertOk();
        $this->as($user)->postJson('/api/watchlist', ['tmdb_id' => 1399, 'media_type' => 'tv', 'status' => 'watched'])->assertCreated();

        $this->as($user)->getJson("/api/users/{$user->username}")
            ->assertJsonPath('top4', [550])
            ->assertJsonPath('watched', []);
    }
}
