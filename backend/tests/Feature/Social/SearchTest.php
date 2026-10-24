<?php

namespace Tests\Feature\Social;

use App\Models\MovieList;
use App\Models\User;

class SearchTest extends SocialTestCase
{
    public function test_search_finds_people_by_name_or_handle_with_follow_state(): void
    {
        $me = User::factory()->create(['name' => 'Maya Rivera']);
        $lina = User::factory()->create(['name' => 'Lina Saad', 'username' => 'linasaad']);
        $omar = User::factory()->create(['name' => 'Omar Khalil', 'username' => 'omark']);
        $me->following()->attach($lina->id);
        $omar->following()->attach($me->id);

        $this->as($me)->getJson('/api/users?q=lina')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('users.0.username', 'linasaad')
            ->assertJsonPath('users.0.is_following', true)
            ->assertJsonPath('users.0.followers', 1);

        $this->as($me)->getJson('/api/users?q=@omark')
            ->assertJsonPath('users.0.is_following', false)
            ->assertJsonPath('users.0.follows_you', true);

        // Never finds yourself.
        $this->as($me)->getJson('/api/users?q=maya')->assertJsonPath('total', 0);
    }

    public function test_underscores_in_the_query_are_matched_literally(): void
    {
        $me = User::factory()->create();
        User::factory()->create(['name' => 'Film Fan', 'username' => 'film_fan']);
        User::factory()->create(['name' => 'Filmxfan', 'username' => 'filmxfan']);

        $this->as($me)->getJson('/api/users?q=film_')->assertJsonPath('total', 1)->assertJsonPath('users.0.username', 'film_fan');
    }

    public function test_without_a_query_the_most_followed_members_come_first(): void
    {
        $me = User::factory()->create();
        $quiet = User::factory()->create(['name' => 'Aaron Quiet']);
        $popular = User::factory()->create(['name' => 'Zed Popular']);
        User::factory()->count(2)->create()->each(fn (User $fan) => $fan->following()->attach($popular->id));

        $this->as($me)->getJson('/api/users?limit=2')
            ->assertJsonPath('users.0.id', $popular->id)
            ->assertJsonCount(2, 'users');
        $this->assertNotNull($quiet);
    }

    public function test_public_lists_can_be_searched_by_title(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        MovieList::create(['user_id' => $other->id, 'title' => 'Rainy Day Comfort', 'is_public' => true]);
        MovieList::create(['user_id' => $other->id, 'title' => 'Under 90 Minutes', 'is_public' => true]);
        MovieList::create(['user_id' => $other->id, 'title' => 'Rainy secrets', 'is_public' => false]);

        $this->as($me)->getJson('/api/lists?scope=public&q=rainy')
            ->assertJsonCount(1, 'lists')
            ->assertJsonPath('lists.0.title', 'Rainy Day Comfort');
    }
}
