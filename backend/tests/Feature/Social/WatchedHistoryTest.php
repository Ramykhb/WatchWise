<?php

namespace Tests\Feature\Social;

use App\Models\User;
use App\Models\UserPreference;
use App\Models\Watchlist;

class WatchedHistoryTest extends SocialTestCase
{
    public function test_all_movies_and_shows_can_be_browsed_without_duplicates(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 55) as $index) {
            Watchlist::create(['user_id' => $user->id, 'tmdb_id' => (int) ceil($index / 2),
                'media_type' => $index % 2 ? 'movie' : 'tv', 'status' => 'watched',
                'watched_at' => $index === 1 ? null : now()->startOfDay()]);
        }
        $profile = $this->as($user)->getJson("/api/users/{$user->username}")->assertOk()->assertJsonPath('counts.films', 55);
        $page = $profile->json('watched_page');
        $items = $page['items'];
        $this->assertCount(24, $items);
        while ($page['next_cursor']) {
            $page = $this->getJson("/api/users/{$user->username}/watched?cursor=".urlencode($page['next_cursor']))->assertOk()->json();
            array_push($items, ...$page['items']);
        }
        $this->assertFalse($page['has_more']);
        $this->assertCount(55, array_unique(array_column($items, 'id')));
        $this->assertCount(55, array_unique(array_map(fn ($item) => $item['media_type'].'-'.$item['tmdb_id'], $items)));
        $this->assertContains('tv', array_column($items, 'media_type'));
    }

    public function test_history_privacy_applies_to_every_page_and_owner_can_still_browse(): void
    {
        $owner = User::factory()->create(['profile_visibility' => 'followers']);
        $viewer = User::factory()->create();
        $url = "/api/users/{$owner->username}/watched";
        $this->as($viewer)->getJson($url)->assertForbidden();
        $viewer->following()->attach($owner->id);
        $this->getJson($url)->assertOk();
        UserPreference::create(['user_id' => $owner->id, 'settings' => ['privacy' => ['history' => false]]]);
        $this->getJson($url)->assertForbidden();
        $this->as($owner)->getJson($url)->assertOk();
    }

    public function test_invalid_cursors_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->as($user)->getJson("/api/users/{$user->username}/watched?cursor=bad")->assertUnprocessable();
    }
}
