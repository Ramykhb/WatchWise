<?php

namespace Tests\Feature\MovieList;

use App\Models\MovieList;
use App\Models\User;
use App\Models\UserPreference;
use Tests\Feature\Social\SocialTestCase;

class MovieListPrivacyTest extends SocialTestCase
{
    private function listOf(User $owner): MovieList
    {
        return MovieList::create(['user_id' => $owner->id, 'title' => 'Picks', 'is_public' => true]);
    }

    /** The three places a list can leak: the public scope, the following scope and a direct GET. */
    private function assertListVisible(User $viewer, MovieList $list, bool $visible): void
    {
        $count = $visible ? 1 : 0;

        $this->as($viewer)->getJson('/api/lists?scope=public')->assertJsonCount($count, 'lists');
        $this->as($viewer)->getJson('/api/lists?scope=following')->assertJsonCount($count, 'lists');
        $this->as($viewer)->getJson("/api/lists/{$list->id}")->assertStatus($visible ? 200 : 403);
    }

    public function test_the_lists_privacy_switch_hides_lists_from_everyone_but_the_owner_and_admins(): void
    {
        $owner = User::factory()->create();
        $follower = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $follower->following()->attach($owner->id);
        $list = $this->listOf($owner);

        $this->assertListVisible($follower, $list, true);

        UserPreference::create(['user_id' => $owner->id, 'settings' => ['privacy' => ['lists' => false]]]);

        $this->assertListVisible($follower, $list, false);
        $this->as($owner)->getJson('/api/lists?scope=mine')->assertJsonCount(1, 'lists');
        $this->as($owner)->getJson("/api/lists/{$list->id}")->assertOk();
        $this->as($admin)->getJson("/api/lists/{$list->id}")->assertOk();
    }

    public function test_lists_of_disabled_accounts_are_hidden(): void
    {
        $owner = User::factory()->create(['is_active' => false]);
        $follower = User::factory()->create();
        $follower->following()->attach($owner->id);
        $list = $this->listOf($owner);

        $this->assertListVisible($follower, $list, false);
        $this->as(User::factory()->admin()->create())->getJson("/api/lists/{$list->id}")->assertOk();
    }

    public function test_public_lists_of_a_private_profile_are_hidden_even_from_followers(): void
    {
        $owner = User::factory()->private()->create();
        $follower = User::factory()->create();
        $follower->following()->attach($owner->id);
        $list = $this->listOf($owner);

        $this->assertListVisible($follower, $list, false);
        $this->as($owner)->getJson("/api/lists/{$list->id}")->assertOk();
    }

    public function test_lists_of_a_followers_only_profile_are_visible_to_followers_only(): void
    {
        $owner = User::factory()->create(['profile_visibility' => 'followers']);
        $follower = User::factory()->create();
        $stranger = User::factory()->create();
        $follower->following()->attach($owner->id);
        $list = $this->listOf($owner);

        $this->assertListVisible($follower, $list, true);
        $this->as($stranger)->getJson('/api/lists?scope=public')->assertJsonCount(0, 'lists');
        $this->as($stranger)->getJson("/api/lists/{$list->id}")->assertForbidden();
    }
}
