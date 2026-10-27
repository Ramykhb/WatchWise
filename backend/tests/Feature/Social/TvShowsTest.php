<?php

namespace Tests\Feature\Social;

use App\Models\User;

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
