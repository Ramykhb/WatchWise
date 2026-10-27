<?php

namespace Tests\Feature\Admin;

use App\Genre\DTOs\Responses\GenreResponse;
use App\Genre\InterfaceServices\GenreServiceInterface;
use App\Models\DiscussionRoom;
use App\Models\LocalMovie;
use App\Models\Message;
use App\Models\MovieList;
use App\Models\Review;
use App\Models\User;
use App\Models\Watchlist;
use App\Support\Exceptions\TmdbUnavailable;
use Mockery;

class AdminStatsTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(GenreServiceInterface::class, Mockery::mock(GenreServiceInterface::class, fn ($mock) => $mock
            ->shouldReceive('all')->andReturn([new GenreResponse(18, 'Drama'), new GenreResponse(35, 'Comedy')])));
    }

    public function test_it_counts_users_and_only_reviews_with_text(): void
    {
        $admin = $this->admin();
        User::factory()->count(2)->create();
        User::factory()->create(['created_at' => now()->subDays(20)]);
        $this->review(['tmdb_id' => 1, 'is_approved' => false]);
        $this->review(['tmdb_id' => 2, 'is_approved' => true]);
        $this->review(['tmdb_id' => 3, 'content' => null, 'sentiment' => null, 'is_approved' => true]);

        $this->as($admin)->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('users_total', 7)
            ->assertJsonPath('new_users.last_7_days', 6)
            ->assertJsonPath('new_users.last_30_days', 7)
            ->assertJsonPath('reviews_total', 2)
            ->assertJsonPath('reviews_pending', 1)
            ->assertJsonPath('new_reviews.last_7_days', 2);
    }

    public function test_most_watched_counts_watched_entries_and_skips_hidden_films(): void
    {
        $admin = $this->admin();
        LocalMovie::factory()->create(['tmdb_id' => 10, 'title' => 'Popular']);
        LocalMovie::factory()->hidden()->create(['tmdb_id' => 20, 'title' => 'Hidden']);

        foreach (User::factory()->count(3)->create() as $user) {
            Watchlist::create(['user_id' => $user->id, 'tmdb_id' => 10, 'status' => 'watched', 'watched_at' => now()]);
            Watchlist::create(['user_id' => $user->id, 'tmdb_id' => 20, 'status' => 'watched', 'watched_at' => now()]);
            Watchlist::create(['user_id' => $user->id, 'tmdb_id' => 30, 'status' => 'to_watch']);
        }

        $response = $this->as($admin)->getJson('/api/admin/stats')->assertOk();

        $response->assertJsonCount(1, 'most_watched')
            ->assertJsonPath('most_watched.0.tmdb_id', 10)
            ->assertJsonPath('most_watched.0.title', 'Popular')
            ->assertJsonPath('most_watched.0.count', 3);
    }

    public function test_top_rated_averages_approved_ratings_and_needs_a_minimum_count(): void
    {
        $admin = $this->admin();
        LocalMovie::factory()->create(['tmdb_id' => 10, 'title' => 'Steady']);

        foreach ([5, 4, 4] as $rating) {
            $this->review(['tmdb_id' => 10, 'rating' => $rating, 'content' => null, 'sentiment' => null, 'is_approved' => true]);
        }
        // One 5-star rating is not enough to rank; an unapproved review does not count.
        $this->review(['tmdb_id' => 20, 'rating' => 5, 'content' => null, 'sentiment' => null, 'is_approved' => true]);
        $this->review(['tmdb_id' => 10, 'rating' => 1, 'is_approved' => false]);

        $this->as($admin)->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonCount(1, 'top_rated')
            ->assertJsonPath('top_rated.0.tmdb_id', 10)
            ->assertJsonPath('top_rated.0.count', 3)
            ->assertJsonPath('top_rated.0.average', 4.33);
    }

    public function test_popular_genres_share_watched_and_rated_films(): void
    {
        $admin = $this->admin();
        LocalMovie::factory()->create(['tmdb_id' => 10, 'genre_ids' => [18]]);
        LocalMovie::factory()->create(['tmdb_id' => 20, 'genre_ids' => [18, 35]]);

        Watchlist::create(['user_id' => User::factory()->create()->id, 'tmdb_id' => 10, 'status' => 'watched', 'watched_at' => now()]);
        $this->review(['tmdb_id' => 20, 'content' => null, 'sentiment' => null, 'is_approved' => true]);

        $this->as($admin)->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('popular_genres.0.name', 'Drama')
            ->assertJsonPath('popular_genres.0.count', 2)
            ->assertJsonPath('popular_genres.0.share', 66.7)
            ->assertJsonPath('popular_genres.1.name', 'Comedy');
    }

    public function test_activity_is_a_daily_series_with_zeros_for_quiet_days(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create();
        $room = DiscussionRoom::create(['tmdb_id' => 603, 'name' => 'The Matrix', 'created_by' => $member->id]);
        Message::create(['room_id' => $room->id, 'user_id' => $member->id, 'content' => 'Hi']);
        MovieList::create(['user_id' => $member->id, 'title' => 'Faves', 'is_public' => true]);
        $this->review(['content' => null, 'sentiment' => null, 'is_approved' => true]);

        $response = $this->as($admin)->getJson('/api/admin/stats?days=7')->assertOk();

        $response->assertJsonCount(7, 'activity')
            ->assertJsonPath('activity.0.signups', 0)
            ->assertJsonPath('activity.6.date', today()->toDateString())
            ->assertJsonPath('activity.6.signups', 3)
            ->assertJsonPath('activity.6.messages', 1)
            ->assertJsonPath('activity.6.lists', 1)
            ->assertJsonPath('activity.6.ratings', 1)
            ->assertJsonPath('activity.6.reviews', 0);
    }

    public function test_the_activity_window_is_limited(): void
    {
        $this->as($this->admin())->getJson('/api/admin/stats?days=500')->assertUnprocessable();
    }

    public function test_genres_fall_back_when_tmdb_names_are_unavailable(): void
    {
        $this->app->instance(GenreServiceInterface::class, Mockery::mock(GenreServiceInterface::class, fn ($mock) => $mock
            ->shouldReceive('all')->andThrow(new TmdbUnavailable)));

        LocalMovie::factory()->create(['tmdb_id' => 10, 'genre_ids' => [18]]);
        $this->review(['tmdb_id' => 10, 'content' => null, 'sentiment' => null, 'is_approved' => true]);

        $this->as($this->admin())->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('popular_genres.0.name', 'Genre 18');
    }
}
