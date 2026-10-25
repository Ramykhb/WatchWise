<?php

namespace Tests\Feature\Social;

use App\Models\MovieList;
use App\Models\User;

class ProfileTest extends SocialTestCase
{
    public function test_new_users_get_a_unique_username(): void
    {
        $first = User::factory()->create(['name' => 'Maya Rivera']);
        $second = User::factory()->create(['name' => 'Maya Rivera']);

        $this->assertSame('mayarivera', $first->username);
        $this->assertSame('mayarivera2', $second->username);
    }

    public function test_social_routes_require_sign_in(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->getJson('/api/users/anyone')->assertUnauthorized();
    }

    public function test_me_returns_account_preferences_and_counts(): void
    {
        $user = User::factory()->create(['bio' => 'Hello', 'location' => 'Beirut']);

        $this->as($user)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.username', $user->username)
            ->assertJsonPath('user.bio', 'Hello')
            ->assertJsonPath('user.location', 'Beirut')
            ->assertJsonPath('preferences.privacy.reviews', true)
            ->assertJsonPath('counts.unread_notifications', 0);
    }

    public function test_updating_profile_and_preferences(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['username' => 'taken']);

        $this->as($user)->patchJson('/api/me', ['username' => 'taken'])->assertUnprocessable();
        $this->as($user)->patchJson('/api/me', ['name' => 'New Name', 'username' => '@New.Handle', 'bio' => 'Bio', 'profile_visibility' => 'followers'])
            ->assertOk()
            ->assertJsonPath('user.username', 'new.handle')
            ->assertJsonPath('user.profile_visibility', 'followers');

        $this->as($user)->patchJson('/api/me/preferences', ['favorite_genres' => ['Drama'], 'privacy' => ['reviews' => false, 'unknown' => true]])
            ->assertOk()
            ->assertJsonPath('preferences.favorite_genres', ['Drama'])
            ->assertJsonPath('preferences.privacy.reviews', false);
        $this->assertArrayNotHasKey('unknown', $user->preferences()->first()->settings['privacy']);
    }

    public function test_password_change_checks_the_current_password(): void
    {
        $user = User::factory()->create();

        $this->as($user)->putJson('/api/me/password', ['current_password' => 'wrong', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->as($user)->putJson('/api/me/password', ['current_password' => 'password', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertOk();
    }

    public function test_profile_shows_public_lists_and_counts(): void
    {
        $viewer = User::factory()->create();
        $owner = User::factory()->create();
        MovieList::create(['user_id' => $owner->id, 'title' => 'Public', 'is_public' => true]);
        MovieList::create(['user_id' => $owner->id, 'title' => 'Secret', 'is_public' => false]);

        $this->as($viewer)->getJson("/api/users/{$owner->username}")
            ->assertOk()
            ->assertJsonPath('is_me', false)
            ->assertJsonPath('counts.lists', 1)
            ->assertJsonCount(1, 'lists')
            ->assertJsonPath('lists.0.title', 'Public');

        $this->as($owner)->getJson("/api/users/{$owner->username}")
            ->assertJsonPath('is_me', true)
            ->assertJsonCount(2, 'lists');
    }

    public function test_private_and_followers_only_profiles_are_restricted(): void
    {
        $viewer = User::factory()->create();
        $private = User::factory()->private()->create();
        $followersOnly = User::factory()->create(['profile_visibility' => 'followers']);

        $this->as($viewer)->getJson("/api/users/{$private->username}")->assertOk()->assertJsonPath('restricted', true);
        $this->as($viewer)->getJson("/api/users/{$followersOnly->username}")->assertJsonPath('restricted', true);

        $viewer->following()->attach($followersOnly->id);
        $this->as($viewer)->getJson("/api/users/{$followersOnly->username}")->assertJsonPath('restricted', false);
    }

    public function test_follow_and_unfollow(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $this->as($me)->postJson("/api/users/{$other->username}/follow")->assertOk()->assertJson(['following' => true, 'followers' => 1]);
        $this->as($me)->postJson("/api/users/{$other->username}/follow")->assertOk();

        $this->as($me)->deleteJson("/api/users/{$other->username}/follow")->assertOk()->assertJson(['following' => false, 'followers' => 0]);
        $this->as($me)->postJson("/api/users/{$me->username}/follow")->assertForbidden();
    }

    public function test_search_members(): void
    {
        $me = User::factory()->create();
        User::factory()->create(['name' => 'Lina Saad', 'username' => 'linasaad']);
        User::factory()->create(['name' => 'Omar Khalil']);

        $this->as($me)->getJson('/api/users?q=lina')->assertOk()->assertJsonCount(1, 'users')->assertJsonPath('users.0.username', 'linasaad');
    }
}
