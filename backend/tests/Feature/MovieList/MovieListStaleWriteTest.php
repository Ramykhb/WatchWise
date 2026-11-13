<?php

namespace Tests\Feature\MovieList;

use App\Models\MovieList;
use App\Models\User;
use Tests\Feature\Social\SocialTestCase;

class MovieListStaleWriteTest extends SocialTestCase
{
    /** A list with two films, last touched a minute ago so a bump is visible. */
    private function listFor(User $user): MovieList
    {
        $list = MovieList::create(['user_id' => $user->id, 'title' => 'Faves', 'is_public' => true]);
        $list->items()->create(['tmdb_id' => 550, 'position' => 0]);
        $list->items()->create(['tmdb_id' => 603, 'position' => 1]);
        MovieList::whereKey($list->id)->toBase()->update(['updated_at' => now()->subMinute()]);

        return $list->refresh();
    }

    public function test_a_matching_expected_updated_at_saves_and_returns_the_new_one(): void
    {
        $user = User::factory()->create();
        $list = $this->listFor($user);

        $response = $this->as($user)->patchJson("/api/lists/{$list->id}", [
            'tmdb_ids' => [603, 550],
            'expected_updated_at' => $list->updated_at->toJSON(),
        ])->assertOk()->assertJsonPath('list.items.0.tmdb_id', 603);

        $this->assertNotSame($list->updated_at->toJSON(), $response->json('list.updated_at'));
    }

    public function test_a_stale_expected_updated_at_is_rejected_and_changes_nothing(): void
    {
        $user = User::factory()->create();
        $list = $this->listFor($user);
        $loaded = $list->updated_at->toJSON();
        // Another tab added a film after this one loaded the list.
        $list->items()->create(['tmdb_id' => 13, 'position' => 2]);
        MovieList::whereKey($list->id)->toBase()->update(['updated_at' => now()]);

        $this->as($user)->patchJson("/api/lists/{$list->id}", [
            'title' => 'Renamed',
            'tmdb_ids' => [603, 550],
            'expected_updated_at' => $loaded,
        ])->assertStatus(409)->assertJsonPath('message', fn (string $m) => $m !== '');

        $this->assertSame('Faves', $list->fresh()->title);
        $this->assertSame([550, 603, 13], $list->items()->orderBy('position')->pluck('tmdb_id')->all());
    }

    public function test_omitting_expected_updated_at_saves_as_before(): void
    {
        $user = User::factory()->create();
        $list = $this->listFor($user);

        $this->as($user)->patchJson("/api/lists/{$list->id}", ['tmdb_ids' => [603]])
            ->assertOk()
            ->assertJsonCount(1, 'list.items');
    }

    public function test_an_invalid_expected_updated_at_is_a_validation_error(): void
    {
        $user = User::factory()->create();
        $list = $this->listFor($user);

        $this->as($user)->patchJson("/api/lists/{$list->id}", ['expected_updated_at' => 'yesterday-ish'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expected_updated_at');
    }
}
