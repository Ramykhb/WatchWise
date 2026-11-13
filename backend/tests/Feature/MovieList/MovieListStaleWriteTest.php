<?php

namespace Tests\Feature\MovieList;

use App\Models\MovieList;
use App\Models\User;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Feature\Social\SocialTestCase;

class MovieListStaleWriteTest extends SocialTestCase
{
    public function test_returns_409_for_a_stale_revision_within_the_same_second(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $list = $this->listFor($user);
        $list->forceFill(['updated_at' => now()])->saveQuietly();
        $loaded = $this->as($user)->getJson("/api/lists/{$list->id}")->assertOk()->json('list');

        $saved = $this->as($user)->patchJson("/api/lists/{$list->id}", [
            'title' => 'Renamed',
            'expected_revision' => $loaded['revision'],
        ])->assertOk()->json('list');

        $this->assertSame($loaded['updated_at'], $saved['updated_at']);
        $this->assertSame(2, $saved['revision']);
        $this->as($user)->patchJson("/api/lists/{$list->id}", [
            'title' => 'Stale overwrite',
            'tmdb_ids' => [603],
            'expected_revision' => $loaded['revision'],
        ])->assertStatus(409);
        $this->assertDatabaseHas('movie_lists', ['id' => $list->id, 'title' => 'Renamed', 'revision' => 2]);
        $this->assertSame([550, 603], $list->items()->pluck('tmdb_id')->all());
    }

    #[TestWith(['patch', '', ['tmdb_ids' => [603, 550]]])]
    #[TestWith(['post', '/items', ['tmdb_id' => 1399, 'media_type' => 'tv']])]
    #[TestWith(['delete', '/items/550', []])]
    public function test_item_writes_invalidate_a_loaded_revision(string $method, string $suffix, array $payload): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $list = $this->listFor($user);
        $path = "/api/lists/{$list->id}";

        $this->as($user)->json($method, $path.$suffix, $payload)
            ->assertOk()->assertJsonPath('list.revision', 2);

        $this->as($user)->patchJson($path, ['title' => 'Stale overwrite', 'expected_revision' => 1])
            ->assertStatus(409);
        $this->assertDatabaseHas('movie_lists', ['id' => $list->id, 'title' => 'Faves', 'revision' => 2]);
    }

    public function test_no_op_writes_keep_the_loaded_revision_valid(): void
    {
        $user = User::factory()->create();
        $list = $this->listFor($user);
        $path = "/api/lists/{$list->id}";

        $this->as($user)->patchJson($path, ['title' => 'Faves', 'tmdb_ids' => [550, 603], 'expected_revision' => 1])
            ->assertOk()->assertJsonPath('list.revision', 1);
        $this->as($user)->postJson($path.'/items', ['tmdb_id' => 550])->assertOk()->assertJsonPath('list.revision', 1);
        $this->as($user)->deleteJson($path.'/items/13')->assertOk()->assertJsonPath('list.revision', 1);
        $this->assertDatabaseHas('movie_lists', ['id' => $list->id, 'revision' => 1]);
    }

    public function test_returns_422_for_an_invalid_expected_revision(): void
    {
        $user = User::factory()->create();
        $list = $this->listFor($user);

        $this->as($user)->patchJson("/api/lists/{$list->id}", ['title' => 'Invalid', 'expected_revision' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors('expected_revision');
        $this->assertDatabaseHas('movie_lists', ['id' => $list->id, 'title' => 'Faves', 'revision' => 1]);
    }

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
