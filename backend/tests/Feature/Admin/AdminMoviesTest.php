<?php

namespace Tests\Feature\Admin;

use App\Models\LocalMovie;

class AdminMoviesTest extends AdminTestCase
{
    public function test_the_local_catalogue_is_listed_and_searchable(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 550, 'title' => 'Fight Club']);
        LocalMovie::factory()->create(['tmdb_id' => 603, 'title' => 'The Matrix']);

        $admin = $this->admin();

        $this->as($admin)->getJson('/api/admin/movies')->assertOk()->assertJsonPath('meta.total', 2);
        $this->as($admin)->getJson('/api/admin/movies?q=matrix')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.tmdb_id', 603)
            ->assertJsonPath('data.0.is_hidden', false);
    }

    public function test_an_admin_can_hide_feature_and_edit_a_film(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 550, 'title' => 'Fight Club']);

        $this->as($this->admin())->patchJson('/api/admin/movies/550', [
            'title' => 'Fight Club (Local)',
            'overview' => 'Edited.',
            'is_hidden' => true,
            'is_featured' => true,
        ])
            ->assertOk()
            ->assertJsonPath('movie.title', 'Fight Club (Local)')
            ->assertJsonPath('movie.is_hidden', true);

        $this->assertDatabaseHas('movies', ['tmdb_id' => 550, 'title' => 'Fight Club (Local)', 'overview' => 'Edited.', 'is_hidden' => true, 'is_featured' => true]);
    }

    public function test_an_explicit_null_clears_the_overview_but_leaving_it_out_keeps_it(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 550, 'overview' => 'Original.']);

        $this->as($this->admin())->patchJson('/api/admin/movies/550', ['is_featured' => true])->assertOk();
        $this->assertDatabaseHas('movies', ['tmdb_id' => 550, 'overview' => 'Original.']);

        $this->as($this->admin())->patchJson('/api/admin/movies/550', ['overview' => null])
            ->assertOk()
            ->assertJsonPath('movie.overview', null);
        $this->assertDatabaseHas('movies', ['tmdb_id' => 550, 'overview' => null, 'is_featured' => true]);
    }

    public function test_fields_that_are_left_out_stay_as_they_were(): void
    {
        LocalMovie::factory()->featured()->create(['tmdb_id' => 550, 'title' => 'Fight Club']);

        $this->as($this->admin())->patchJson('/api/admin/movies/550', ['is_hidden' => true])->assertOk();

        $this->assertDatabaseHas('movies', ['tmdb_id' => 550, 'title' => 'Fight Club', 'is_hidden' => true, 'is_featured' => true]);
    }

    public function test_a_film_that_is_not_in_the_catalogue_is_not_found(): void
    {
        $this->as($this->admin())->patchJson('/api/admin/movies/1', ['is_hidden' => true])->assertNotFound();
    }

    public function test_the_edit_is_validated(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 550]);

        $this->as($this->admin())->patchJson('/api/admin/movies/550', ['title' => '', 'is_hidden' => 'maybe'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'is_hidden']);
    }
}
