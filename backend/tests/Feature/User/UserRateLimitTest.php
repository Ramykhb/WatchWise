<?php

namespace Tests\Feature\User;

use App\Models\User;
use Tests\Feature\Social\SocialTestCase;

class UserRateLimitTest extends SocialTestCase
{
    public function test_user_search_is_limited_to_60_a_minute(): void
    {
        $viewer = User::factory()->create();

        for ($i = 0; $i < 60; $i++) {
            $this->as($viewer)->getJson('/api/users?q=a')->assertOk();
        }

        $this->as($viewer)->getJson('/api/users?q=a')->assertStatus(429);
    }

    public function test_follow_unfollow_and_request_answers_share_a_60_a_minute_bucket(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();

        for ($i = 0; $i < 30; $i++) {
            $this->as($viewer)->deleteJson("/api/users/{$other->username}/follow")->assertOk();
            $this->as($viewer)->deleteJson("/api/me/follow-requests/{$other->username}")->assertOk();
        }

        $this->as($viewer)->deleteJson("/api/users/{$other->username}/follow")->assertStatus(429);
        // Another member has their own bucket.
        $this->as($other)->deleteJson("/api/users/{$viewer->username}/follow")->assertOk();
    }

    public function test_avatar_upload_and_delete_share_a_10_a_minute_bucket(): void
    {
        $viewer = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->as($viewer)->deleteJson('/api/me/avatar')->assertOk();
        }

        $this->as($viewer)->postJson('/api/me/avatar')->assertStatus(429);
    }

    public function test_profile_and_preference_updates_share_a_30_a_minute_bucket(): void
    {
        $viewer = User::factory()->create();

        for ($i = 0; $i < 15; $i++) {
            $this->as($viewer)->patchJson('/api/me', ['bio' => 'Hello'])->assertOk();
            $this->as($viewer)->patchJson('/api/me/preferences', ['favorite_genres' => ['Drama']])->assertOk();
        }

        $this->as($viewer)->patchJson('/api/me', ['bio' => 'Hello'])->assertStatus(429);
    }
}
