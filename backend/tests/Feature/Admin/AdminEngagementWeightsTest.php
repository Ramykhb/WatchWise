<?php

namespace Tests\Feature\Admin;

use App\Admin\InterfaceRepository\AdminStatsRepositoryInterface;
use App\Models\User;
use App\Models\Watchlist;

class AdminEngagementWeightsTest extends AdminTestCase
{
    private function watch(int $tmdbId, int $times, string $mediaType = 'movie'): void
    {
        foreach (User::factory()->count($times)->create() as $user) {
            Watchlist::create(['user_id' => $user->id, 'tmdb_id' => $tmdbId, 'media_type' => $mediaType, 'status' => 'watched', 'watched_at' => now()]);
        }
    }

    private function rate(int $tmdbId, int $times, bool $approved = true, string $mediaType = 'movie'): void
    {
        for ($count = 0; $count < $times; $count++) {
            $this->review(['tmdb_id' => $tmdbId, 'media_type' => $mediaType, 'content' => null, 'sentiment' => null, 'is_approved' => $approved]);
        }
    }

    private function weights(array $hidden, int $limit): array
    {
        return $this->app->make(AdminStatsRepositoryInterface::class)->engagementWeights($hidden, $limit);
    }

    public function test_watches_and_approved_ratings_are_added_up_per_film_heaviest_first(): void
    {
        $this->watch(10, 2);
        $this->rate(10, 3);
        $this->watch(20, 4);
        $this->rate(30, 1);

        $this->assertSame([10 => 5, 20 => 4, 30 => 1], $this->weights([], 10));
    }

    public function test_ties_are_broken_by_the_lower_tmdb_id(): void
    {
        $this->watch(30, 2);
        $this->watch(10, 1);
        $this->rate(10, 1);
        $this->rate(20, 2);

        $this->assertSame([10 => 2, 20 => 2, 30 => 2], $this->weights([], 10));
    }

    public function test_only_the_heaviest_films_up_to_the_limit_are_returned(): void
    {
        foreach ([1 => 1, 2 => 5, 3 => 3, 4 => 4, 5 => 2] as $tmdbId => $times) {
            $this->watch($tmdbId, $times);
        }

        $this->assertSame([2 => 5, 4 => 4, 3 => 3], $this->weights([], 3));
    }

    public function test_hidden_films_unapproved_ratings_unfinished_watches_and_tv_shows_are_left_out(): void
    {
        $this->watch(10, 2);
        $this->watch(20, 5);
        $this->rate(20, 2);
        $this->rate(30, 4, approved: false);
        $this->watch(40, 6, 'tv');
        $this->rate(40, 6, mediaType: 'tv');
        Watchlist::create(['user_id' => User::factory()->create()->id, 'tmdb_id' => 50, 'status' => 'to_watch']);

        $this->assertSame([10 => 2], $this->weights([20], 10));
    }

    public function test_nothing_to_rank_gives_an_empty_array(): void
    {
        $this->assertSame([], $this->weights([], 10));
    }
}
