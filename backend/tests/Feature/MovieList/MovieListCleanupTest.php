<?php

namespace Tests\Feature\MovieList;

use App\Models\MovieList;
use App\Models\User;
use Tests\Feature\Social\SocialTestCase;

class MovieListCleanupTest extends SocialTestCase
{
    public function test_lists_carry_a_typed_cover_and_no_posters_field(): void
    {
        $user = User::factory()->create();
        $list = MovieList::create(['user_id' => $user->id, 'title' => 'Mixed', 'is_public' => true]);
        $list->items()->create(['tmdb_id' => 1399, 'media_type' => 'tv', 'position' => 0]);

        $this->as($user)->getJson('/api/lists?scope=mine')
            ->assertOk()
            ->assertJsonMissingPath('lists.0.posters')
            ->assertJsonPath('lists.0.cover.0.tmdb_id', 1399)
            ->assertJsonPath('lists.0.cover.0.media_type', 'tv');

        $this->as($user)->getJson("/api/lists/{$list->id}")
            ->assertOk()
            ->assertJsonMissingPath('list.posters');
    }

    public function test_the_search_text_has_a_maximum_length(): void
    {
        $user = User::factory()->create();

        $this->as($user)->getJson('/api/lists?q='.str_repeat('a', 101))->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->as($user)->getJson('/api/lists?q='.str_repeat('a', 100))->assertOk();
    }

    public function test_oversize_tmdb_ids_are_rejected(): void
    {
        $user = User::factory()->create();
        $list = MovieList::create(['user_id' => $user->id, 'title' => 'Mine', 'is_public' => true]);

        $this->as($user)->getJson('/api/lists?contains=2147483648')->assertUnprocessable()->assertJsonValidationErrors('contains');
        $this->as($user)->postJson("/api/lists/{$list->id}/items", ['tmdb_id' => 2147483648])->assertUnprocessable()->assertJsonValidationErrors('tmdb_id');
        $this->as($user)->postJson('/api/lists', ['title' => 'X', 'tmdb_ids' => ['9999999999']])->assertUnprocessable()->assertJsonValidationErrors('tmdb_ids.0');
        $this->as($user)->postJson('/api/lists', ['title' => 'X', 'tmdb_ids' => ['tv-12345678901']])->assertUnprocessable()->assertJsonValidationErrors('tmdb_ids.0');
        $this->as($user)->patchJson("/api/lists/{$list->id}", ['tmdb_ids' => [2147483648]])->assertUnprocessable()->assertJsonValidationErrors('tmdb_ids.0');

        $this->as($user)->postJson('/api/lists', ['title' => 'X', 'tmdb_ids' => ['2147483647']])->assertCreated();
    }

    public function test_removing_a_tv_show_only_removes_the_show(): void
    {
        $user = User::factory()->create();
        $list = MovieList::create(['user_id' => $user->id, 'title' => 'Mixed', 'is_public' => false]);
        $list->items()->create(['tmdb_id' => 1399, 'media_type' => 'movie', 'position' => 0]);
        $list->items()->create(['tmdb_id' => 1399, 'media_type' => 'tv', 'position' => 1]);

        $this->as($user)->deleteJson("/api/lists/{$list->id}/items/1399?type=tv")
            ->assertOk()
            ->assertJsonPath('list.films', 1)
            ->assertJsonPath('list.items.0.media_type', 'movie');
    }
}
