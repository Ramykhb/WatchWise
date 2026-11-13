<?php

namespace Tests\Feature\Admin;

use App\Models\LocalMovie;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminRatingsTest extends AdminRatingTestCase
{
    public function test_the_list_holds_star_only_ratings_newest_first_with_author_and_film(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 550, 'title' => 'Fight Club', 'poster_path' => '/fc.jpg']);
        $old = $this->rating(['rating' => 2], now()->subDays(2));
        $new = $this->rating(['rating' => 5, 'tmdb_id' => 603], now()->subHour());
        $emptyText = $this->rating(['tmdb_id' => 8, 'content' => ''], now()->subMinutes(5));
        $this->rating(['tmdb_id' => 7, 'content' => 'A review, not a rating.', 'sentiment' => 'positive']);

        $response = $this->as($this->admin())->getJson('/api/admin/ratings')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.id', $emptyText->id)
            ->assertJsonPath('data.1.id', $new->id)
            ->assertJsonPath('data.2.id', $old->id)
            ->assertJsonPath('data.2.tmdb_id', 550)
            ->assertJsonPath('data.2.media_type', 'movie')
            ->assertJsonPath('data.2.rating', 2)
            ->assertJsonPath('data.2.author.id', $old->user_id)
            ->assertJsonPath('data.2.author.username', $old->user->username)
            ->assertJsonPath('data.2.film', ['tmdb_id' => 550, 'title' => 'Fight Club', 'poster_path' => '/fc.jpg'])
            ->assertJsonPath('data.1.film', null);

        $this->assertSame(
            ['id', 'tmdb_id', 'media_type', 'rating', 'moderated', 'author', 'film', 'created_at'],
            array_keys($response->json('data.2')),
        );
    }

    public function test_a_tv_show_has_no_film_even_when_a_movie_shares_its_id(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 550, 'title' => 'Fight Club']);
        $this->rating(['media_type' => 'tv']);

        $this->as($this->admin())->getJson('/api/admin/ratings')
            ->assertOk()
            ->assertJsonPath('data.0.media_type', 'tv')
            ->assertJsonPath('data.0.film', null);
    }

    public function test_ratings_left_at_the_same_moment_are_ordered_by_id_newest_first(): void
    {
        $moment = now()->subMinute();
        $first = $this->rating([], $moment);
        $second = $this->rating(['tmdb_id' => 2], $moment);
        $third = $this->rating(['tmdb_id' => 3], $moment);

        $this->as($this->admin())->getJson('/api/admin/ratings')
            ->assertOk()
            ->assertJsonPath('data.0.id', $third->id)
            ->assertJsonPath('data.1.id', $second->id)
            ->assertJsonPath('data.2.id', $first->id);
    }

    public function test_filters_narrow_the_list_and_combine(): void
    {
        $maya = User::factory()->create(['name' => 'Maya Stone', 'username' => 'maya_s']);
        $leo = User::factory()->create(['name' => 'Leo Park', 'username' => 'leopark']);
        $target = $this->rating(['user_id' => $maya->id, 'tmdb_id' => 10, 'rating' => 1]);
        $this->rating(['user_id' => $maya->id, 'tmdb_id' => 11, 'rating' => 5]);
        $this->rating(['user_id' => $leo->id, 'tmdb_id' => 10, 'rating' => 1]);
        $this->rating(['user_id' => $maya->id, 'tmdb_id' => 10, 'media_type' => 'tv', 'rating' => 1]);
        $this->as($this->admin());

        $this->getJson('/api/admin/ratings?tmdb_id=10')->assertJsonPath('meta.total', 3);
        $this->getJson('/api/admin/ratings?media_type=tv')->assertJsonPath('meta.total', 1);
        $this->getJson("/api/admin/ratings?user_id={$maya->id}")->assertJsonPath('meta.total', 3);
        $this->getJson('/api/admin/ratings?rating=1')->assertJsonPath('meta.total', 3);
        $this->getJson("/api/admin/ratings?tmdb_id=10&media_type=movie&user_id={$maya->id}&rating=1")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $target->id);
    }

    public function test_the_search_matches_names_and_usernames_and_treats_wildcards_literally(): void
    {
        $maya = User::factory()->create(['name' => 'Maya Stone', 'username' => 'maya_s']);
        $other = User::factory()->create(['name' => 'Mayaxs', 'username' => 'plain']);
        $this->rating(['user_id' => $maya->id]);
        $this->rating(['user_id' => $other->id, 'tmdb_id' => 2]);
        $this->as($this->admin());

        $this->getJson('/api/admin/ratings?q=STONE')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.author.id', $maya->id);
        $this->getJson('/api/admin/ratings?q=maya_s')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.author.id', $maya->id);
        $this->getJson('/api/admin/ratings?q=maya')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/admin/ratings?q=%25')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/admin/ratings?q=nobody')->assertJsonPath('meta.total', 0);
    }

    public function test_the_summary_covers_the_whole_filtered_set_and_ignores_reviews_with_text(): void
    {
        foreach ([1, 2, 5, 5] as $stars) {
            $this->rating(['rating' => $stars, 'tmdb_id' => 10]);
        }
        $this->rating(['rating' => 3, 'tmdb_id' => 11]);
        $this->rating(['rating' => 1, 'tmdb_id' => 10, 'content' => 'Text.', 'sentiment' => 'negative']);
        $this->as($this->admin());

        $all = $this->getJson('/api/admin/ratings?per_page=2')->assertOk();
        $all->assertJsonCount(2, 'data')
            ->assertJsonPath('summary.total', 5)
            ->assertJsonPath('summary.average', 3.2)
            ->assertJsonPath('summary.distribution', ['1' => 1, '2' => 1, '3' => 1, '4' => 0, '5' => 2]);
        $this->assertStringContainsString('"distribution":{"1":1,"2":1,"3":1,"4":0,"5":2}', $all->getContent());

        $this->getJson('/api/admin/ratings?tmdb_id=10')
            ->assertJsonPath('summary.total', 4)
            ->assertJsonPath('summary.average', 3.25)
            ->assertJsonPath('summary.distribution.5', 2);
        $this->getJson('/api/admin/ratings?rating=4')
            ->assertJsonPath('summary.total', 0)
            ->assertJsonPath('summary.average', null)
            ->assertJsonPath('summary.distribution', ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0]);
    }

    public function test_the_list_is_paged(): void
    {
        for ($id = 1; $id <= 5; $id++) {
            $this->rating(['tmdb_id' => $id], now()->subMinutes($id));
        }

        $this->as($this->admin())->getJson('/api/admin/ratings?per_page=2&page=3')
            ->assertOk()
            ->assertJsonPath('meta', ['current_page' => 3, 'last_page' => 3, 'per_page' => 2, 'total' => 5])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tmdb_id', 5);
    }

    public function test_the_filters_are_validated(): void
    {
        $this->as($this->admin());

        foreach (['rating=0', 'rating=6', 'rating=x', 'media_type=book', 'tmdb_id=0', 'user_id=abc', 'q[]=a', 'page=0', 'per_page=51', 'per_page=0'] as $filter) {
            $this->getJson('/api/admin/ratings?'.$filter)->assertUnprocessable();
        }
    }

    public function test_the_query_count_does_not_grow_with_the_page(): void
    {
        $admin = $this->admin();
        foreach (range(1, 3) as $id) {
            LocalMovie::factory()->create(['tmdb_id' => $id]);
            $this->rating(['tmdb_id' => $id]);
        }
        $this->as($admin)->getJson('/api/admin/ratings')->assertOk();

        DB::enableQueryLog();
        $this->getJson('/api/admin/ratings')->assertOk()->assertJsonCount(3, 'data');
        $few = count(DB::getQueryLog());

        foreach (range(4, 20) as $id) {
            LocalMovie::factory()->create(['tmdb_id' => $id]);
            $this->rating(['tmdb_id' => $id]);
        }
        DB::flushQueryLog();
        $this->getJson('/api/admin/ratings')->assertOk()->assertJsonCount(20, 'data');

        $this->assertSame($few, count(DB::getQueryLog()));
    }

    public function test_one_films_picture_adds_up_its_star_ratings(): void
    {
        $this->travelTo(now()->startOfSecond());
        LocalMovie::factory()->create(['tmdb_id' => 550, 'title' => 'Fight Club', 'poster_path' => '/fc.jpg']);
        $this->rating(['rating' => 5], now()->subDays(3));
        $this->rating(['rating' => 4], now()->subDay());
        $this->rating(['rating' => 4], now()->subHour());
        $this->rating(['rating' => 1, 'content' => 'Text.', 'sentiment' => 'negative']);
        $this->rating(['rating' => 1, 'tmdb_id' => 603]);
        $this->rating(['rating' => 1, 'media_type' => 'tv']);

        $this->as($this->admin())->getJson('/api/admin/ratings/films/550')
            ->assertOk()
            ->assertJsonPath('tmdb_id', 550)
            ->assertJsonPath('media_type', 'movie')
            ->assertJsonPath('film', ['tmdb_id' => 550, 'title' => 'Fight Club', 'poster_path' => '/fc.jpg'])
            ->assertJsonPath('total', 3)
            ->assertJsonPath('average', 4.33)
            ->assertJsonPath('distribution', ['1' => 0, '2' => 0, '3' => 0, '4' => 2, '5' => 1])
            ->assertJsonPath('with_text_count', 1)
            ->assertJsonPath('first_rated_at', now()->subDays(3)->toJSON())
            ->assertJsonPath('last_rated_at', now()->subHour()->toJSON());
    }

    public function test_a_tv_show_and_a_title_nobody_rated_are_answered_too(): void
    {
        $this->rating(['media_type' => 'tv', 'tmdb_id' => 1399, 'rating' => 3]);
        $this->as($this->admin());

        $this->getJson('/api/admin/ratings/films/1399?media_type=tv')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('average', 3)->assertJsonPath('film', null);
        $this->getJson('/api/admin/ratings/films/1399')
            ->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/admin/ratings/films/999')
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('average', null)
            ->assertJsonPath('distribution', ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0])
            ->assertJsonPath('with_text_count', 0)
            ->assertJsonPath('first_rated_at', null)
            ->assertJsonPath('last_rated_at', null);
        $this->getJson('/api/admin/ratings/films/550?media_type=book')->assertUnprocessable();
        $this->getJson('/api/admin/ratings/films/abc')->assertNotFound();
    }

    public function test_the_read_endpoints_are_for_staff_only(): void
    {
        $this->rating();

        foreach (['/api/admin/ratings', '/api/admin/ratings/films/550', '/api/admin/ratings/bursts'] as $url) {
            $this->getJson($url)->assertUnauthorized();
            $this->as(User::factory()->create())->getJson($url)->assertForbidden();
            $this->as($this->moderator())->getJson($url)->assertOk();
            $this->as($this->admin())->getJson($url)->assertOk();
            $this->flushHeaders();
        }
    }
}
