<?php

namespace Tests\Feature\Admin;

use App\Models\LocalMovie;
use App\Models\User;

/** What the dashboard counts besides users, reviews and films: reports, star ratings, rooms, lists and the account states. */
class AdminStatsOverviewTest extends AdminStatsTestCase
{
    public function test_it_counts_reports_ratings_rooms_and_lists(): void
    {
        $this->seedWorld();

        $this->as($this->admin())->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('reports', [
                'open' => 5,
                'by_target_type' => ['review' => 1, 'message' => 1, 'list' => 1, 'room' => 2, 'profile' => 0],
                'resolved_last_7_days' => 1,
            ])
            ->assertJsonPath('ratings', ['total' => 4, 'new_last_7_days' => 2, 'new_last_30_days' => 3, 'average' => 3.5])
            ->assertJsonPath('rooms', ['total' => 4, 'public' => 2, 'private' => 2, 'locked' => 2, 'reported' => 2])
            ->assertJsonPath('lists', ['total' => 4, 'public' => 2, 'private' => 2, 'reported' => 1])
            ->assertJsonPath('flagged_reviews', 1);
    }

    public function test_it_counts_suspended_and_unverified_accounts_next_to_the_total(): void
    {
        $this->seedWorld();

        $response = $this->as($this->admin())->getJson('/api/admin/stats')->assertOk();

        $this->assertSame(User::count(), $response->json('users_total'));
        $this->assertSame(2, $response->json('users_suspended'));
        $this->assertSame(1, $response->json('users_unverified'));
    }

    public function test_the_new_figures_leave_the_existing_fields_as_they_were(): void
    {
        $this->seedWorld();

        $this->as($this->admin())->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonStructure([
                'users_total', 'users_suspended', 'users_unverified', 'new_users' => ['last_7_days', 'last_30_days'],
                'reviews_total', 'reviews_pending', 'new_reviews' => ['last_7_days', 'last_30_days'],
                'active_today', 'active_yesterday', 'flagged_reviews',
                'reports' => ['open', 'by_target_type' => ['review', 'message', 'list', 'room', 'profile'], 'resolved_last_7_days'],
                'ratings' => ['total', 'new_last_7_days', 'new_last_30_days', 'average'],
                'rooms' => ['total', 'public', 'private', 'locked', 'reported'],
                'lists' => ['total', 'public', 'private', 'reported'],
                'range' => ['name', 'days', 'new_users', 'previous_new_users', 'new_reviews', 'previous_new_reviews', 'new_ratings', 'previous_new_ratings', 'new_lists', 'previous_new_lists'],
                'most_watched', 'top_rated', 'popular_genres', 'activity',
            ])
            ->assertJsonPath('reviews_total', 1)
            ->assertJsonPath('reviews_pending', 0);
    }

    public function test_an_empty_site_has_zeros_for_every_new_figure_and_no_average(): void
    {
        $this->as($this->admin())->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('users_suspended', 0)
            ->assertJsonPath('users_unverified', 0)
            ->assertJsonPath('reports', ['open' => 0, 'by_target_type' => ['review' => 0, 'message' => 0, 'list' => 0, 'room' => 0, 'profile' => 0], 'resolved_last_7_days' => 0])
            ->assertJsonPath('ratings', ['total' => 0, 'new_last_7_days' => 0, 'new_last_30_days' => 0, 'average' => null])
            ->assertJsonPath('rooms', ['total' => 0, 'public' => 0, 'private' => 0, 'locked' => 0, 'reported' => 0])
            ->assertJsonPath('lists', ['total' => 0, 'public' => 0, 'private' => 0, 'reported' => 0])
            ->assertJsonPath('range.new_ratings', 0)
            ->assertJsonPath('range.previous_new_ratings', 0)
            ->assertJsonPath('range.new_lists', 0)
            ->assertJsonPath('range.previous_new_lists', 0);
    }

    public function test_a_week_window_compares_new_ratings_and_lists_with_the_week_before(): void
    {
        $this->seedWorld();

        $this->as($this->admin())->getJson('/api/admin/stats?range=7')
            ->assertOk()
            ->assertJsonPath('range.new_ratings', 2)
            ->assertJsonPath('range.previous_new_ratings', 1)
            ->assertJsonPath('range.new_lists', 2)
            ->assertJsonPath('range.previous_new_lists', 1);
    }

    public function test_a_month_window_moves_the_boundaries_of_both_periods(): void
    {
        $this->seedWorld();
        $admin = $this->admin();

        $this->as($admin)->getJson('/api/admin/stats?range=30')
            ->assertOk()
            ->assertJsonPath('range.new_ratings', 3)
            ->assertJsonPath('range.previous_new_ratings', 1)
            ->assertJsonPath('range.new_lists', 3)
            ->assertJsonPath('range.previous_new_lists', 1);

        $this->getJson('/api/admin/stats?days=14')
            ->assertJsonPath('range.new_ratings', 3)
            ->assertJsonPath('range.previous_new_ratings', 0)
            ->assertJsonPath('range.new_lists', 3)
            ->assertJsonPath('range.previous_new_lists', 0);
    }

    public function test_the_window_includes_today_and_excludes_tomorrow_and_the_day_before_the_previous_window(): void
    {
        $this->rating(5, today()->startOfDay());
        $this->rating(4, today()->subDays(6)->startOfDay());
        $this->rating(3, today()->subDays(7)->endOfDay());
        $this->rating(2, today()->subDays(13)->startOfDay());
        $this->rating(1, today()->subDays(14)->endOfDay());
        $this->listCreatedAt(today()->subDays(6)->startOfDay());
        $this->listCreatedAt(today()->subDays(7)->endOfDay());

        $this->as($this->admin())->getJson('/api/admin/stats?range=7')
            ->assertOk()
            ->assertJsonPath('range.new_ratings', 2)
            ->assertJsonPath('range.previous_new_ratings', 2)
            ->assertJsonPath('range.new_lists', 1)
            ->assertJsonPath('range.previous_new_lists', 1);
    }

    public function test_ratings_are_star_only_rows_moderated_ones_included_and_hidden_films_do_not_matter(): void
    {
        LocalMovie::factory()->hidden()->create(['tmdb_id' => 20]);
        $this->rating(5, null, ['tmdb_id' => 20]);
        $this->rating(3, null, ['removed_at' => now()]);
        $this->rating(4, null, ['media_type' => 'tv']);
        $this->review(['content' => 'Text, so a review.', 'rating' => 1, 'is_approved' => true]);
        $this->review(['content' => '', 'rating' => 5, 'tmdb_id' => 7, 'is_approved' => true]);

        $this->as($this->admin())->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('ratings.total', 4)
            ->assertJsonPath('ratings.new_last_7_days', 4)
            ->assertJsonPath('ratings.average', 4.25)
            ->assertJsonPath('reviews_total', 1);
    }

    public function test_a_moderator_sees_the_same_figures_as_an_admin(): void
    {
        $this->seedWorld();
        $admin = $this->admin();
        $moderator = $this->moderator();

        $asAdmin = $this->as($admin)->getJson('/api/admin/stats')->assertOk()->json();
        $asModerator = $this->as($moderator)->getJson('/api/admin/stats')->assertOk()->json();

        foreach (['reports', 'ratings', 'rooms', 'lists', 'users_suspended', 'users_unverified'] as $key) {
            $this->assertSame($asAdmin[$key], $asModerator[$key]);
        }
    }

    public function test_members_and_guests_still_cannot_read_the_dashboard(): void
    {
        $this->getJson('/api/admin/stats')->assertUnauthorized();
        $this->as(User::factory()->create())->getJson('/api/admin/stats')->assertForbidden();
    }
}
