<?php

namespace Tests\Feature\User;

use App\Models\LocalMovie;
use App\Models\User;
use Tests\Concerns\UsesMovieCatalogue;
use Tests\Feature\Social\SocialTestCase;

class HomeFeaturedTest extends SocialTestCase
{
    use UsesMovieCatalogue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useRealCatalogue();
    }

    /**
     * @return list<int>
     */
    private function featuredIds(): array
    {
        return collect($this->as(User::factory()->create())->getJson('/api/home')->assertOk()->json('featured'))->pluck('id')->all();
    }

    public function test_home_has_an_empty_featured_row_when_nothing_is_featured(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 1]);

        $this->as(User::factory()->create())->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('featured', [])
            ->assertJsonStructure(['activity', 'rooms', 'next_night', 'featured']);
    }

    public function test_a_featured_film_shows_as_a_movie_card(): void
    {
        config(['services.tmdb.image_url' => 'https://img.test/t/p']);
        LocalMovie::factory()->featured()->create([
            'tmdb_id' => 550,
            'title' => 'Fight Club (Local)',
            'overview' => 'Edited.',
            'poster_path' => '/club.jpg',
            'release_date' => '1999-10-15',
            'genre_ids' => [18, 53],
        ]);

        $this->as(User::factory()->create())->getJson('/api/home')
            ->assertOk()
            ->assertJsonCount(1, 'featured')
            ->assertJsonPath('featured.0', [
                'id' => 550,
                'media_type' => 'movie',
                'title' => 'Fight Club (Local)',
                'overview' => 'Edited.',
                'release_date' => '1999-10-15',
                'year' => 1999,
                'rating' => 0,
                'vote_count' => 0,
                'genre_ids' => [18, 53],
                'poster_url' => 'https://img.test/t/p/w500/club.jpg',
                'backdrop_url' => null,
            ]);
    }

    public function test_a_featured_film_without_a_poster_or_date_still_shows(): void
    {
        LocalMovie::factory()->featured()->create(['tmdb_id' => 7, 'poster_path' => null, 'release_date' => null, 'overview' => null]);

        $this->as(User::factory()->create())->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('featured.0.poster_url', null)
            ->assertJsonPath('featured.0.release_date', null)
            ->assertJsonPath('featured.0.year', null)
            ->assertJsonPath('featured.0.overview', '');
    }

    public function test_a_hidden_film_is_not_featured_even_when_it_is_flagged(): void
    {
        LocalMovie::factory()->featured()->create(['tmdb_id' => 1]);
        LocalMovie::factory()->featured()->hidden()->create(['tmdb_id' => 2]);
        LocalMovie::factory()->create(['tmdb_id' => 3]);

        $this->assertSame([1], $this->featuredIds());
    }

    public function test_the_most_recently_edited_film_comes_first(): void
    {
        LocalMovie::factory()->featured()->create(['tmdb_id' => 1, 'updated_at' => now()->subDays(3)]);
        LocalMovie::factory()->featured()->create(['tmdb_id' => 2, 'updated_at' => now()->subDay()]);
        LocalMovie::factory()->featured()->create(['tmdb_id' => 3, 'updated_at' => now()->subDays(2)]);

        $this->assertSame([2, 3, 1], $this->featuredIds());
    }

    public function test_the_row_holds_at_most_ten_films(): void
    {
        foreach (range(1, 12) as $id) {
            LocalMovie::factory()->featured()->create(['tmdb_id' => $id, 'updated_at' => now()->subMinutes($id)]);
        }

        $this->assertSame(range(1, 10), $this->featuredIds());
    }

    public function test_featuring_hiding_and_unfeaturing_through_the_admin_applies_on_the_next_request(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 550]);
        $admin = User::factory()->admin()->create();

        $this->as($admin)->patchJson('/api/admin/movies/550', ['is_featured' => true])->assertOk();
        $this->assertSame([550], $this->featuredIds());

        $this->as($admin)->patchJson('/api/admin/movies/550', ['is_hidden' => true])->assertOk();
        $this->assertSame([], $this->featuredIds());

        $this->as($admin)->patchJson('/api/admin/movies/550', ['is_hidden' => false])->assertOk();
        $this->assertSame([550], $this->featuredIds());

        $this->as($admin)->patchJson('/api/admin/movies/550', ['is_featured' => false])->assertOk();
        $this->assertSame([], $this->featuredIds());
    }
}
