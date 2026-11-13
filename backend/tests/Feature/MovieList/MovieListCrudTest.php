<?php

namespace Tests\Feature\MovieList;

use App\Models\MovieList;
use App\Models\User;
use Tests\Feature\Social\SocialTestCase;

class MovieListCrudTest extends SocialTestCase
{
    public function test_list_crud_and_reorder(): void
    {
        $user = User::factory()->create();

        $id = $this->as($user)->postJson('/api/lists', ['title' => 'Comedies', 'is_public' => true, 'tmdb_ids' => [3, 1, 2]])
            ->assertCreated()
            ->assertJsonPath('list.films', 3)
            ->assertJsonPath('list.items.0.tmdb_id', 3)
            ->json('list.id');

        $this->as($user)->patchJson("/api/lists/{$id}", ['tmdb_ids' => [2, 3]])
            ->assertOk()
            ->assertJsonPath('list.films', 2)
            ->assertJsonPath('list.items.0.tmdb_id', 2);

        $this->as($user)->getJson('/api/lists?scope=mine')->assertJsonCount(1, 'lists');
        $this->as($user)->deleteJson("/api/lists/{$id}")->assertOk();
        $this->assertDatabaseMissing('movie_lists', ['id' => $id]);
    }

    public function test_lists_are_private_to_their_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $list = MovieList::create(['user_id' => $owner->id, 'title' => 'Secret', 'is_public' => false]);

        $this->as($other)->getJson("/api/lists/{$list->id}")->assertForbidden();
        $this->as($other)->patchJson("/api/lists/{$list->id}", ['title' => 'Mine now'])->assertForbidden();
        $this->as($other)->deleteJson("/api/lists/{$list->id}")->assertForbidden();
    }

    public function test_following_scope_shows_public_lists_of_followed_users(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $stranger = User::factory()->create();
        MovieList::create(['user_id' => $friend->id, 'title' => 'Friend public', 'is_public' => true]);
        MovieList::create(['user_id' => $friend->id, 'title' => 'Friend private', 'is_public' => false]);
        MovieList::create(['user_id' => $stranger->id, 'title' => 'Stranger', 'is_public' => true]);
        $me->following()->attach($friend->id);

        $this->as($me)->getJson('/api/lists?scope=following')->assertJsonCount(1, 'lists')->assertJsonPath('lists.0.title', 'Friend public');
        $this->as($me)->getJson('/api/lists?scope=public')->assertJsonCount(2, 'lists');
    }

    public function test_lists_follow_the_profile_visibility_rule(): void
    {
        $follower = User::factory()->create();
        $stranger = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $open = User::factory()->create();
        $private = User::factory()->private()->create();
        $followersOnly = User::factory()->create(['profile_visibility' => 'followers']);
        foreach ([$open, $private, $followersOnly] as $owner) {
            MovieList::create(['user_id' => $owner->id, 'title' => "{$owner->profile_visibility} picks", 'is_public' => true]);
        }
        $follower->following()->attach([$private->id, $followersOnly->id]);
        $privateList = MovieList::where('user_id', $private->id)->sole();

        $this->as($stranger)->getJson('/api/lists?scope=public')->assertJsonCount(1, 'lists')->assertJsonPath('lists.0.title', 'public picks');
        // Public profile: everyone; followers-only: followers; private: nobody but the owner and admins.
        $this->as($follower)->getJson('/api/lists?scope=public')->assertJsonCount(2, 'lists');
        $this->as($follower)->getJson('/api/lists?scope=following')->assertJsonCount(1, 'lists')->assertJsonPath('lists.0.title', 'followers picks');

        $this->as($stranger)->getJson("/api/lists/{$privateList->id}")->assertForbidden();
        $this->as($follower)->getJson("/api/lists/{$privateList->id}")->assertForbidden();
        $this->as($private)->getJson("/api/lists/{$privateList->id}")->assertOk();
        $this->as($admin)->getJson("/api/lists/{$privateList->id}")->assertOk();
    }
}
