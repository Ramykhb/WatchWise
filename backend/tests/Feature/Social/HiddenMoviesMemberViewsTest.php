<?php

namespace Tests\Feature\Social;

use App\Models\MovieList;
use App\Models\Review;
use App\Models\User;
use Tests\Concerns\UsesMovieCatalogue;

class HiddenMoviesMemberViewsTest extends SocialTestCase
{
    use UsesMovieCatalogue;

    private const HIDDEN = 101;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useRealCatalogue();
        $this->hide(self::HIDDEN);
    }

    /**
     * @param  list<int|string>  $films  TMDB ids, "tv-<id>" for a TV show
     */
    private function listOf(User $owner, array $films, bool $public = true): MovieList
    {
        $list = MovieList::create(['user_id' => $owner->id, 'title' => 'Picks', 'is_public' => $public]);
        foreach ($films as $position => $film) {
            $isTv = str_starts_with((string) $film, 'tv-');
            $list->items()->create([
                'tmdb_id' => (int) str_replace('tv-', '', (string) $film),
                'media_type' => $isTv ? 'tv' : 'movie',
                'position' => $position,
            ]);
        }

        return $list;
    }

    public function test_other_members_do_not_see_a_hidden_movie_in_a_list(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $viewer->following()->attach($owner);
        $list = $this->listOf($owner, [self::HIDDEN, 'tv-'.self::HIDDEN, 7]);

        $this->as($viewer)->getJson("/api/lists/{$list->id}")
            ->assertOk()
            ->assertJsonPath('list.films', 2)
            ->assertJsonPath('list.items.0.media_type', 'tv')
            ->assertJsonPath('list.items.1.tmdb_id', 7)
            ->assertJsonCount(2, 'list.items');

        foreach (['public', 'following'] as $scope) {
            $this->as($viewer)->getJson("/api/lists?scope={$scope}")
                ->assertOk()
                ->assertJsonPath('lists.0.films', 2)
                ->assertJsonPath('lists.0.cover.0.media_type', 'tv')
                ->assertJsonPath('lists.0.posters', [self::HIDDEN, 7]);
        }
    }

    public function test_the_owner_still_sees_the_hidden_movie_in_their_own_lists(): void
    {
        $owner = User::factory()->create();
        $list = $this->listOf($owner, [self::HIDDEN, 7], public: false);

        $this->as($owner)->getJson("/api/lists/{$list->id}")
            ->assertOk()
            ->assertJsonPath('list.films', 2)
            ->assertJsonPath('list.items.0.tmdb_id', self::HIDDEN);
        $this->as($owner)->getJson('/api/lists?scope=mine')
            ->assertOk()
            ->assertJsonPath('lists.0.films', 2)
            ->assertJsonPath('lists.0.posters', [self::HIDDEN, 7]);
        $this->assertDatabaseCount('movie_list_items', 2);
    }

    public function test_a_profile_leaves_the_hidden_movie_out_of_lists_watched_and_top_films_for_other_members(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $this->listOf($owner, [self::HIDDEN, 7]);
        foreach ([self::HIDDEN, 7] as $id) {
            $owner->watchlists()->create(['tmdb_id' => $id, 'media_type' => 'movie', 'status' => 'watched', 'watched_at' => now()]);
            Review::create(['user_id' => $owner->id, 'tmdb_id' => $id, 'media_type' => 'movie', 'rating' => 5, 'content' => null, 'is_approved' => true]);
        }

        $this->as($viewer)->getJson('/api/users/'.$owner->username)
            ->assertOk()
            ->assertJsonPath('watched', [7])
            ->assertJsonPath('top4', [7])
            ->assertJsonPath('counts.films', 1)
            ->assertJsonPath('lists.0.films', 1)
            ->assertJsonPath('lists.0.posters', [7]);

        $this->as($owner)->getJson('/api/users/'.$owner->username)
            ->assertOk()
            ->assertJsonCount(2, 'watched')
            ->assertJsonPath('counts.films', 2)
            ->assertJsonPath('lists.0.films', 2);
    }

    public function test_the_home_activity_feed_leaves_out_hidden_movies(): void
    {
        $viewer = User::factory()->create();
        $friend = User::factory()->create();
        $viewer->following()->attach($friend);
        $this->travel(-10)->minutes();
        Review::create(['user_id' => $friend->id, 'tmdb_id' => self::HIDDEN, 'media_type' => 'movie', 'rating' => 5, 'content' => null, 'is_approved' => true]);
        $friend->watchlists()->create(['tmdb_id' => self::HIDDEN, 'media_type' => 'movie', 'status' => 'watched', 'watched_at' => now()]);
        $friend->watchlists()->create(['tmdb_id' => 7, 'media_type' => 'movie', 'status' => 'watched', 'watched_at' => now()]);
        $friend->watchlists()->create(['tmdb_id' => self::HIDDEN, 'media_type' => 'tv', 'status' => 'watched', 'watched_at' => now()]);
        $this->listOf($friend, [self::HIDDEN, 8]);
        $this->travelBack();

        $feed = collect($this->as($viewer)->getJson('/api/home')->assertOk()->json('activity'));

        $this->assertEqualsCanonicalizing(
            [['list', 'movie', 8], ['watched', 'movie', 7], ['watched', 'tv', self::HIDDEN]],
            $feed->map(fn (array $item): array => [$item['kind'], $item['media_type'], $item['tmdb_id']])->all(),
        );
    }
}
