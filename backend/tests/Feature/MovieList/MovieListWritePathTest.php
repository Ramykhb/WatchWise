<?php

namespace Tests\Feature\MovieList;

use App\Models\LocalMovie;
use App\Models\MovieList;
use App\Models\User;
use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Social\SocialTestCase;

class MovieListWritePathTest extends SocialTestCase
{
    private function listFor(User $user): MovieList
    {
        return MovieList::create(['user_id' => $user->id, 'title' => 'Faves', 'is_public' => true]);
    }

    /** A list that was last touched long ago, so a bump is visible in the same second. */
    private function staleListFor(User $user): MovieList
    {
        $list = $this->listFor($user);
        $list->items()->create(['tmdb_id' => 550, 'position' => 0, 'note' => 'Rewatch', 'added_at' => '2026-01-01 10:00:00']);
        $list->items()->create(['tmdb_id' => 603, 'position' => 1]);
        MovieList::whereKey($list->id)->toBase()->update(['updated_at' => '2026-01-01 10:00:00']);

        return $list->refresh();
    }

    public function test_reordering_films_already_in_the_catalogue_makes_no_tmdb_request(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $list = $this->staleListFor($user);
        LocalMovie::factory()->create(['tmdb_id' => 550]);
        LocalMovie::factory()->create(['tmdb_id' => 603]);

        $this->as($user)->patchJson("/api/lists/{$list->id}", ['tmdb_ids' => [603, 550]])
            ->assertOk()
            ->assertJsonPath('list.items.0.tmdb_id', 603);

        Http::assertNothingSent();
    }

    public function test_a_reorder_does_not_remember_any_film(): void
    {
        $user = User::factory()->create();
        $list = $this->staleListFor($user);
        $this->mock(MovieCatalogServiceInterface::class)->shouldNotReceive('remember');

        $this->as($user)->patchJson("/api/lists/{$list->id}", ['tmdb_ids' => [603, 550]])->assertOk();
    }

    public function test_only_newly_added_films_are_remembered(): void
    {
        $user = User::factory()->create();
        $list = $this->staleListFor($user);
        $this->mock(MovieCatalogServiceInterface::class)
            ->shouldReceive('remember')->once()->with(13);

        $this->as($user)->patchJson("/api/lists/{$list->id}", ['tmdb_ids' => [550, 603, 13]])->assertOk();
    }

    public function test_a_failing_catalogue_lookup_does_not_break_the_save(): void
    {
        $user = User::factory()->create();
        $list = $this->staleListFor($user);
        $this->mock(MovieCatalogServiceInterface::class)
            ->shouldReceive('remember')->andThrow(new \RuntimeException('TMDB is down'));

        $this->as($user)->patchJson("/api/lists/{$list->id}", ['tmdb_ids' => [550, 603, 13]])
            ->assertOk()
            ->assertJsonPath('list.films', 3);
    }

    public function test_an_empty_patch_keeps_updated_at(): void
    {
        $user = User::factory()->create();
        $list = $this->staleListFor($user);

        $this->as($user)->patchJson("/api/lists/{$list->id}", [])->assertOk();
        $this->as($user)->patchJson("/api/lists/{$list->id}", ['title' => 'Faves', 'tmdb_ids' => [550, 603]])->assertOk();

        $this->assertSame('2026-01-01 10:00:00', $list->fresh()->updated_at->toDateTimeString());
    }

    public function test_changing_a_field_or_the_films_bumps_updated_at(): void
    {
        $user = User::factory()->create();

        foreach ([['title' => 'Renamed'], ['tmdb_ids' => [603, 550]], ['tmdb_ids' => [550]], ['tmdb_ids' => [550, 603, 13]]] as $payload) {
            $list = $this->staleListFor($user);

            $this->as($user)->patchJson("/api/lists/{$list->id}", $payload)->assertOk();

            $this->assertNotSame('2026-01-01 10:00:00', $list->fresh()->updated_at->toDateTimeString());
        }
    }

    public function test_a_reorder_keeps_notes_and_added_at(): void
    {
        $user = User::factory()->create();
        $list = $this->staleListFor($user);

        $this->as($user)->patchJson("/api/lists/{$list->id}", ['tmdb_ids' => [603, 13, 550]])
            ->assertOk()
            ->assertJsonPath('list.films', 3);

        $kept = $list->items()->where('tmdb_id', 550)->first();
        $this->assertSame('Rewatch', $kept->note);
        $this->assertSame('2026-01-01 10:00:00', $kept->added_at->toDateTimeString());
        $this->assertSame([603 => 0, 13 => 1, 550 => 2], $list->items()->pluck('position', 'tmdb_id')->all());
    }

    public function test_adding_to_an_empty_list_starts_at_position_zero(): void
    {
        $user = User::factory()->create();
        $list = $this->listFor($user);

        $this->as($user)->postJson("/api/lists/{$list->id}/items", ['tmdb_id' => 550])->assertOk();
        $this->as($user)->postJson("/api/lists/{$list->id}/items", ['tmdb_id' => 603])->assertOk();

        $this->assertSame([550 => 0, 603 => 1], $list->items()->pluck('position', 'tmdb_id')->all());
    }

    public function test_creating_a_list_numbers_films_from_zero_with_an_added_date(): void
    {
        $user = User::factory()->create();

        $this->as($user)->postJson('/api/lists', ['title' => 'New', 'tmdb_ids' => [550, 'tv-1399']])->assertCreated();

        $items = MovieList::firstWhere('title', 'New')->items()->orderBy('position')->get();
        $this->assertSame([0, 1], $items->pluck('position')->all());
        $this->assertNotNull($items[0]->added_at);
    }
}
