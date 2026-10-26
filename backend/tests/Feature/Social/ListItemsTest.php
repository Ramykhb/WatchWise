<?php

namespace Tests\Feature\Social;

use App\Models\MovieList;
use App\Models\User;

class ListItemsTest extends SocialTestCase
{
    public function test_adding_a_film_puts_it_at_the_end_once(): void
    {
        $user = User::factory()->create();
        $list = MovieList::create(['user_id' => $user->id, 'title' => 'Comedies', 'is_public' => true]);
        $list->items()->create(['tmdb_id' => 1001, 'position' => 0]);

        $this->as($user)->postJson("/api/lists/{$list->id}/items", ['tmdb_id' => 550])
            ->assertOk()
            ->assertJsonPath('list.films', 2)
            ->assertJsonPath('list.items.1.tmdb_id', 550);

        // Adding it again changes nothing.
        $this->as($user)->postJson("/api/lists/{$list->id}/items", ['tmdb_id' => 550])
            ->assertOk()
            ->assertJsonPath('list.films', 2);
    }

    public function test_removing_a_film(): void
    {
        $user = User::factory()->create();
        $list = MovieList::create(['user_id' => $user->id, 'title' => 'Comedies', 'is_public' => false]);
        $list->items()->create(['tmdb_id' => 550, 'position' => 0]);

        $this->as($user)->deleteJson("/api/lists/{$list->id}/items/550")->assertOk()->assertJsonPath('list.films', 0);
        $this->as($user)->deleteJson("/api/lists/{$list->id}/items/550")->assertOk();
    }

    public function test_only_the_owner_can_change_the_films(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $list = MovieList::create(['user_id' => $owner->id, 'title' => 'Public', 'is_public' => true]);
        $list->items()->create(['tmdb_id' => 550, 'position' => 0]);

        $this->as($other)->postJson("/api/lists/{$list->id}/items", ['tmdb_id' => 13])->assertForbidden();
        $this->as($other)->deleteJson("/api/lists/{$list->id}/items/550")->assertForbidden();
        $this->as($owner)->postJson("/api/lists/{$list->id}/items", ['tmdb_id' => 'abc'])->assertUnprocessable();
    }

    public function test_lists_say_whether_they_contain_a_film(): void
    {
        $user = User::factory()->create();
        $with = MovieList::create(['user_id' => $user->id, 'title' => 'Has it', 'is_public' => true]);
        $with->items()->create(['tmdb_id' => 550, 'position' => 0]);
        MovieList::create(['user_id' => $user->id, 'title' => 'Empty', 'is_public' => true]);

        $lists = collect($this->as($user)->getJson('/api/lists?scope=mine&contains=550')->assertOk()->json('lists'))->keyBy('title');
        $this->assertTrue($lists['Has it']['has_movie']);
        $this->assertFalse($lists['Empty']['has_movie']);

        // Without `contains` the field is left out.
        $this->assertArrayNotHasKey('has_movie', $this->as($user)->getJson('/api/lists?scope=mine')->json('lists.0'));
    }
}
