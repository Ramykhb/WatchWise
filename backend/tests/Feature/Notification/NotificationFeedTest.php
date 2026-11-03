<?php

namespace Tests\Feature\Notification;

use App\Models\Notification;
use App\Models\User;
use App\Notification\Events\NotificationsChanged;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Social\SocialTestCase;

class NotificationFeedTest extends SocialTestCase
{
    public function test_pending_follow_requests_remain_actionable_beyond_the_history_limit(): void
    {
        [$me, $requester, $follower] = User::factory()->count(3)->create()->all();
        $request = Notification::create(['user_id' => $me->id, 'type' => Notification::FOLLOW_REQUEST, 'pending_follow_requester_id' => $requester->id, 'data' => ['actor_id' => $requester->id]]);
        for ($i = 0; $i < 101; $i++) {
            $this->followedBy($me, $follower);
        }

        $feed = $this->as($me)->getJson('/api/notifications')->assertOk()->assertJsonCount(101, 'notifications');

        $this->assertContains($request->id, array_column($feed->json('notifications'), 'id'));
        $this->as($me)->postJson("/api/me/follow-requests/{$requester->username}/accept")->assertOk();
        $this->assertTrue($me->isFollowedBy($requester->id));
    }

    private function followedBy(User $owner, User $follower): Notification
    {
        return Notification::create(['user_id' => $owner->id, 'type' => Notification::FOLLOW, 'data' => ['actor_id' => $follower->id]]);
    }

    public function test_the_feed_lists_your_notifications_newest_first_without_room_read_receipts(): void
    {
        [$me, $lina] = User::factory()->count(2)->create()->all();
        $follow = $this->followedBy($me, $lina);
        $follow->forceFill(['created_at' => now()->subHour()])->save();
        $invite = Notification::create(['user_id' => $me->id, 'type' => Notification::ROOM_INVITE, 'data' => ['actor_id' => $lina->id, 'room_id' => 7, 'room_name' => 'Horror Night Crew']]);
        Notification::create(['user_id' => $me->id, 'type' => Notification::ROOM_MESSAGE, 'data' => ['room_id' => 7, 'message_id' => 1]]);
        $this->followedBy($lina, $me);

        $this->as($me)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'notifications')
            ->assertJsonPath('notifications.0.id', $invite->id)
            ->assertJsonPath('notifications.0.kind', 'room_invite')
            ->assertJsonPath('notifications.0.actor.username', $lina->username)
            ->assertJsonPath('notifications.0.text', 'invited you to the private room')
            ->assertJsonPath('notifications.0.target', 'Horror Night Crew')
            ->assertJsonPath('notifications.0.to', '/rooms/7')
            ->assertJsonPath('notifications.0.read', false)
            ->assertJsonPath('notifications.1.id', $follow->id)
            ->assertJsonPath('notifications.1.text', 'started following you')
            ->assertJsonPath('notifications.1.to', "/profile/{$lina->username}")
            ->assertJsonPath('notifications.1.actor_followed', false);
    }

    public function test_notifications_whose_actor_is_gone_or_that_lack_their_subject_are_left_out(): void
    {
        [$me, $gone] = User::factory()->count(2)->create()->all();
        $this->followedBy($me, $gone);
        // A row from before notifications stored the room they are about.
        Notification::create(['user_id' => $me->id, 'type' => Notification::MENTION, 'data' => ['actor_id' => $me->id, 'text' => 'mentioned you']]);
        $gone->delete();

        $this->as($me)->getJson('/api/notifications')->assertOk()->assertJsonCount(0, 'notifications');
    }

    public function test_marking_read_unread_and_everything_read_leaves_room_read_receipts_alone(): void
    {
        [$me, $lina, $omar] = User::factory()->count(3)->create()->all();
        $first = $this->followedBy($me, $lina);
        $this->followedBy($me, $omar);
        $receipt = Notification::create(['user_id' => $me->id, 'type' => Notification::ROOM_MESSAGE, 'data' => ['room_id' => 1, 'message_id' => 1]]);

        $this->as($me)->patchJson("/api/notifications/{$first->id}", ['read' => true])->assertOk()->assertExactJson(['read' => true]);
        $this->assertNotNull($first->fresh()->read_at);
        $this->as($me)->patchJson("/api/notifications/{$first->id}", ['read' => false])->assertOk();
        $this->assertNull($first->fresh()->read_at);
        $this->as($me)->getJson('/api/me')->assertJsonPath('counts.unread_notifications', 2);

        $this->as($me)->postJson('/api/notifications/read-all')->assertOk();

        $this->as($me)->getJson('/api/me')->assertJsonPath('counts.unread_notifications', 0);
        $this->assertNull($receipt->fresh()->read_at);
        // The user's other tabs reload their list.
        Event::assertDispatched(NotificationsChanged::class, fn (NotificationsChanged $event): bool => $event->userId === $me->id
            && $event->broadcastOn()[0]->name === "private-notifications.{$me->id}"
            && $event->broadcastConnections() === ['reverb']);
    }

    public function test_dismissing_deletes_the_notification(): void
    {
        [$me, $lina] = User::factory()->count(2)->create()->all();
        $notification = $this->followedBy($me, $lina);

        $this->as($me)->deleteJson("/api/notifications/{$notification->id}")->assertOk();

        $this->assertModelMissing($notification);
    }

    public function test_you_cannot_change_other_peoples_notifications_or_room_read_receipts(): void
    {
        [$me, $lina] = User::factory()->count(2)->create()->all();
        $theirs = $this->followedBy($lina, $me);
        $receipt = Notification::create(['user_id' => $me->id, 'type' => Notification::ROOM_MESSAGE, 'data' => ['room_id' => 1, 'message_id' => 1]]);
        $mine = $this->followedBy($me, $lina);

        foreach ([$theirs, $receipt] as $notification) {
            $this->as($me)->patchJson("/api/notifications/{$notification->id}", ['read' => true])->assertForbidden();
            $this->as($me)->deleteJson("/api/notifications/{$notification->id}")->assertForbidden();
        }
        $this->as($me)->patchJson("/api/notifications/{$mine->id}", ['read' => 'yes'])->assertUnprocessable();

        $this->assertDatabaseCount('notifications', 3);
        $this->assertNull($receipt->fresh()->read_at);
    }

    public function test_every_notification_type_is_on_until_you_switch_it_off(): void
    {
        $me = User::factory()->create();

        $this->as($me)->getJson('/api/me')->assertJsonPath('preferences.notifications', array_fill_keys(Notification::OPTIONAL_TYPES, true));

        $this->as($me)->patchJson('/api/me/preferences', ['notifications' => ['new_messages' => false, 'releases' => false]])
            ->assertOk()
            ->assertJsonPath('preferences.notifications.new_messages', false)
            ->assertJsonPath('preferences.notifications.mention', true)
            ->assertJsonMissingPath('preferences.notifications.releases');
    }

    public function test_the_socket_channel_is_signed_only_for_your_own_notifications(): void
    {
        config([
            'broadcasting.connections.reverb.key' => 'test-socket-key',
            'broadcasting.connections.reverb.secret' => 'test-socket-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        $me = User::factory()->create();
        $channel = "private-notifications.{$me->id}";
        $auth = fn (string $channel) => $this->as($me)->postJson('/api/notifications/socket-auth', ['socket_id' => '123.456', 'channel_name' => $channel]);

        $auth($channel)->assertOk()->assertExactJson(['auth' => 'test-socket-key:'.hash_hmac('sha256', "123.456:{$channel}", 'test-socket-secret')]);
        $auth('private-notifications.'.($me->id + 1))->assertForbidden();
        $auth('private-rooms.public')->assertUnprocessable();

        $this->withHeaders(['Authorization' => ''])->postJson('/api/notifications/socket-auth', ['socket_id' => '123.456', 'channel_name' => $channel])->assertUnauthorized();
    }
}
