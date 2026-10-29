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

    public function test_an_admin_can_override_director_runtime_release_date_and_genres(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 550, 'genre_ids' => [18]]);

        $this->as($this->admin())->patchJson('/api/admin/movies/550', [
            'director' => 'David Fincher',
            'runtime' => 139,
            'release_date' => '1999-10-15',
            'genre_ids' => [18, 53],
        ])
            ->assertOk()
            ->assertJsonPath('movie.director', 'David Fincher')
            ->assertJsonPath('movie.runtime', 139)
            ->assertJsonPath('movie.release_date', '1999-10-15')
            ->assertJsonPath('movie.genre_ids', [18, 53])
            ->assertJsonPath('movie.is_edited', true);

        $this->as($this->admin())->patchJson('/api/admin/movies/550', ['director' => null, 'runtime' => null, 'genre_ids' => []])
            ->assertOk()
            ->assertJsonPath('movie.director', null)
            ->assertJsonPath('movie.runtime', null)
            ->assertJsonPath('movie.genre_ids', [])
            ->assertJsonPath('movie.release_date', '1999-10-15');
    }

    public function test_hiding_a_film_is_not_a_content_override(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 550]);

        $this->as($this->admin())->patchJson('/api/admin/movies/550', ['is_hidden' => true])
            ->assertOk()
            ->assertJsonPath('movie.is_edited', false);
    }

    public function test_the_new_movie_fields_are_validated(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 550]);

        $this->as($this->admin())->patchJson('/api/admin/movies/550', ['runtime' => 0, 'release_date' => 'soon', 'genre_ids' => ['x']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['runtime', 'release_date', 'genre_ids.0']);
    }

    public function test_the_list_summarises_the_sync_state(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 550, 'synced_at' => '2026-10-20 10:00:00']);
        LocalMovie::factory()->create(['tmdb_id' => 603, 'synced_at' => '2026-10-25 10:00:00', 'edited_at' => now()]);

        $this->as($this->admin())->getJson('/api/admin/movies')
            ->assertOk()
            ->assertJsonPath('summary.sync.cached', 2)
            ->assertJsonPath('summary.sync.overrides', 1)
            ->assertJsonPath('summary.sync.last_synced_at', fn ($at) => str_starts_with($at, '2026-10-25'));
    }
}
