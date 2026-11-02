<?php

namespace Tests\Feature\Notification;

use App\Models\Notification;
use App\Models\User;
use App\Notification\Events\NotificationPushed;
use App\Notification\Events\NotificationsChanged;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Social\SocialTestCase;

class FollowRequestTest extends SocialTestCase
{
    public function test_following_a_public_profile_is_instant_and_tells_them_live(): void
    {
        [$me, $lina] = User::factory()->count(2)->create()->all();

        $this->as($me)->postJson("/api/users/{$lina->username}/follow")
            ->assertOk()
            ->assertExactJson(['following' => true, 'requested' => false, 'followers' => 1]);

        $this->as($lina)->getJson('/api/notifications')
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.kind', 'follow')
            ->assertJsonPath('notifications.0.actor.username', $me->username)
            ->assertJsonPath('notifications.0.actor_followed', false);
        Event::assertDispatched(NotificationPushed::class, fn (NotificationPushed $event): bool => $event->userId === $lina->id
            && $event->broadcastOn()[0]->name === "private-notifications.{$lina->id}"
            && $event->broadcastAs() === 'notification.pushed'
            && $event->broadcastWith()['notification']['kind'] === 'follow'
            && $event->broadcastWith()['silent'] === false);
    }

    public function test_following_again_after_unfollowing_does_not_pile_up_notifications(): void
    {
        [$me, $lina] = User::factory()->count(2)->create()->all();

        $this->as($me)->postJson("/api/users/{$lina->username}/follow");
        $this->as($me)->deleteJson("/api/users/{$lina->username}/follow");
        $this->as($me)->postJson("/api/users/{$lina->username}/follow");

        $this->assertSame(1, Notification::where('user_id', $lina->id)->count());
    }

    public function test_followers_only_and_private_profiles_get_a_follow_request(): void
    {
        $me = User::factory()->create();
        $followersOnly = User::factory()->create(['profile_visibility' => 'followers']);
        $private = User::factory()->private()->create();

        foreach ([$followersOnly, $private] as $owner) {
            $this->as($me)->postJson("/api/users/{$owner->username}/follow")
                ->assertOk()
                ->assertExactJson(['following' => false, 'requested' => true, 'followers' => 0]);
            // Asking twice keeps one request.
            $this->as($me)->postJson("/api/users/{$owner->username}/follow")->assertOk()->assertJson(['requested' => true]);

            $this->assertFalse($owner->isFollowedBy($me->id));
            $this->as($owner)->getJson('/api/notifications')
                ->assertJsonCount(1, 'notifications')
                ->assertJsonPath('notifications.0.kind', 'follow_request')
                ->assertJsonPath('notifications.0.text', 'asked to follow you');
        }

        $this->as($me)->getJson("/api/users/{$followersOnly->username}")
            ->assertJsonPath('restricted', true)
            ->assertJsonPath('is_following', false)
            ->assertJsonPath('is_requested', true);
        $found = collect($this->as($me)->getJson('/api/users?q='.$private->username)->json('users'))->firstWhere('username', $private->username);
        $this->assertTrue($found['is_requested']);
    }

    public function test_unfollowing_withdraws_a_pending_request(): void
    {
        $me = User::factory()->create();
        $owner = User::factory()->private()->create();
        $this->as($me)->postJson("/api/users/{$owner->username}/follow");

        $this->as($me)->deleteJson("/api/users/{$owner->username}/follow")
            ->assertOk()
            ->assertExactJson(['following' => false, 'requested' => false, 'followers' => 0]);

        $this->assertDatabaseMissing('notifications', ['type' => Notification::FOLLOW_REQUEST]);
        Event::assertDispatched(NotificationsChanged::class, fn (NotificationsChanged $event): bool => $event->userId === $owner->id);
    }

    public function test_accepting_a_request_makes_them_a_follower_and_tells_them(): void
    {
        $me = User::factory()->create();
        $owner = User::factory()->create(['profile_visibility' => 'followers']);
        $this->as($me)->postJson("/api/users/{$owner->username}/follow");
        $request = Notification::where('type', Notification::FOLLOW_REQUEST)->sole();

        $this->as($owner)->postJson("/api/me/follow-requests/{$me->username}/accept")->assertOk();

        $this->assertTrue($owner->isFollowedBy($me->id));
        // The request is now "started following you", so the owner can follow back from it.
        $this->assertSame(Notification::FOLLOW, $request->fresh()->type);
        $this->as($me)->getJson('/api/notifications')
            ->assertJsonPath('notifications.0.kind', 'follow_accepted')
            ->assertJsonPath('notifications.0.actor.username', $owner->username)
            ->assertJsonPath('notifications.0.text', 'accepted your follow request');
        $this->as($me)->getJson("/api/users/{$owner->username}")
            ->assertJsonPath('restricted', false)
            ->assertJsonPath('is_following', true)
            ->assertJsonPath('is_requested', false);

        $this->as($owner)->postJson("/api/me/follow-requests/{$me->username}/accept")
            ->assertNotFound()
            ->assertJsonPath('message', 'This follow request is no longer pending.');
    }

    public function test_declining_or_dismissing_a_request_removes_it(): void
    {
        [$me, $other] = User::factory()->count(2)->create()->all();
        $owner = User::factory()->private()->create();
        $this->as($me)->postJson("/api/users/{$owner->username}/follow");
        $this->as($other)->postJson("/api/users/{$owner->username}/follow");

        $this->as($owner)->deleteJson("/api/me/follow-requests/{$me->username}")->assertOk();
        $othersRequest = Notification::where('type', Notification::FOLLOW_REQUEST)->sole();
        $this->as($owner)->deleteJson("/api/notifications/{$othersRequest->id}")->assertOk();

        $this->assertDatabaseMissing('notifications', ['type' => Notification::FOLLOW_REQUEST]);
        $this->assertFalse($owner->isFollowedBy($me->id));
        $this->as($me)->getJson("/api/users/{$owner->username}")->assertJsonPath('is_requested', false);
        // Declining again is harmless.
        $this->as($owner)->deleteJson("/api/me/follow-requests/{$me->username}")->assertOk();
    }

    public function test_a_request_sent_before_the_profile_went_public_is_answered_by_following(): void
    {
        $me = User::factory()->create();
        $owner = User::factory()->create(['profile_visibility' => 'followers']);
        $this->as($me)->postJson("/api/users/{$owner->username}/follow");
        $owner->forceFill(['profile_visibility' => 'public'])->save();

        $this->as($me)->postJson("/api/users/{$owner->username}/follow")->assertExactJson(['following' => true, 'requested' => false, 'followers' => 1]);

        $this->assertSame([Notification::FOLLOW], Notification::where('user_id', $owner->id)->pluck('type')->all());
    }

    public function test_switched_off_types_are_not_sent_but_follow_requests_always_arrive(): void
    {
        $me = User::factory()->create();
        $public = User::factory()->create();
        $private = User::factory()->private()->create();
        foreach ([$public, $private] as $owner) {
            $this->as($owner)->patchJson('/api/me/preferences', ['notifications' => ['follow' => false]])
                ->assertOk()
                ->assertJsonPath('preferences.notifications.follow', false);
        }

        $this->as($me)->postJson("/api/users/{$public->username}/follow")->assertOk();
        $this->as($me)->postJson("/api/users/{$private->username}/follow")->assertOk();

        $this->assertSame(0, Notification::where('user_id', $public->id)->count());
        $this->assertSame([Notification::FOLLOW_REQUEST], Notification::where('user_id', $private->id)->pluck('type')->all());

        // Accepted, the request goes away instead of becoming a "new follower" they switched off.
        $this->as($private)->postJson("/api/me/follow-requests/{$me->username}/accept")->assertOk();
        $this->assertSame(0, Notification::where('user_id', $private->id)->count());
        $this->assertSame([Notification::FOLLOW_ACCEPTED], Notification::where('user_id', $me->id)->pluck('type')->all());
    }
}
