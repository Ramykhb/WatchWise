<?php

namespace Tests\Feature\Notification;

use App\Models\DiscussionRoom;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Review;
use App\Models\User;
use App\Notification\Events\NotificationPushed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Social\SocialTestCase;

class NotificationTriggersTest extends SocialTestCase
{
    /**
     * @param  list<User>  $members
     */
    private function room(array $members, bool $private = false): DiscussionRoom
    {
        $room = DiscussionRoom::create(['tmdb_id' => 1003, 'name' => 'Thriller Thursday', 'created_by' => $members[0]->id, 'is_private' => $private]);
        foreach ($members as $member) {
            DB::table('room_members')->insert(['room_id' => $room->id, 'user_id' => $member->id]);
        }

        return $room;
    }

    private function send(User $sender, DiscussionRoom $room, string $content): int
    {
        return $this->as($sender)->postJson("/api/rooms/{$room->id}/messages", ['content' => $content])->assertCreated()->json('message.id');
    }

    /**
     * @return list<string>
     */
    private function feedTypes(User $user): array
    {
        return Notification::where('user_id', $user->id)->whereIn('type', Notification::FEED_TYPES)->orderBy('type')->pluck('type')->all();
    }

    private function createNight(User $host, array $guests): int
    {
        return $this->as($host)->postJson('/api/movie-nights', [
            'title' => 'Friday Frights',
            'scheduled_at' => now()->addDays(3)->toIso8601String(),
            'invitees' => array_map(fn (User $guest): string => $guest->username, $guests),
            'tmdb_ids' => [1003, 1008],
        ])->assertCreated()->json('night.id');
    }

    public function test_a_room_invite_tells_the_invitee_and_joining_tells_whoever_invited_them(): void
    {
        [$lina, $omar] = User::factory()->count(2)->create()->all();
        $room = $this->room([$lina], private: true);

        $this->as($lina)->postJson("/api/rooms/{$room->id}/invites", ['invitees' => [$omar->username]])->assertOk();

        $invite = Notification::where('user_id', $omar->id)->sole();
        $this->as($omar)->getJson('/api/notifications')
            ->assertJsonPath('notifications.0.kind', 'room_invite')
            ->assertJsonPath('notifications.0.text', 'invited you to the private room')
            ->assertJsonPath('notifications.0.target', 'Thriller Thursday');

        $this->as($omar)->postJson("/api/rooms/{$room->id}/join")->assertOk();

        $this->assertNotNull($invite->fresh()->read_at);
        $this->as($lina)->getJson('/api/notifications')
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.kind', 'room_joined')
            ->assertJsonPath('notifications.0.actor.username', $omar->username)
            ->assertJsonPath('notifications.0.text', 'accepted your invite to');
    }

    public function test_declining_a_room_invite_takes_its_notification_away(): void
    {
        [$lina, $omar] = User::factory()->count(2)->create()->all();
        $room = $this->room([$lina], private: true);
        $this->as($lina)->postJson("/api/rooms/{$room->id}/invites", ['invitees' => [$omar->username]])->assertOk();

        $this->as($omar)->deleteJson("/api/rooms/{$room->id}/invite")->assertOk();

        $this->assertSame([], $this->feedTypes($omar));
        $this->assertSame([], $this->feedTypes($lina));
    }

    public function test_new_messages_count_up_in_one_notification_per_room_until_the_room_is_read(): void
    {
        [$lina, $omar, $sam] = User::factory()->count(3)->create()->all();
        $room = $this->room([$lina, $omar, $sam]);
        $this->send($lina, $room, 'Who is still up for a thriller this week?');
        $this->send($sam, $room, 'Always.');
        $this->send($sam, $room, 'Something   slow-burn please.');

        $this->as($omar)->getJson('/api/notifications')
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.kind', 'new_messages')
            ->assertJsonPath('notifications.0.actor.username', $sam->username)
            ->assertJsonPath('notifications.0.text', 'and 1 other sent 3 new messages in')
            ->assertJsonPath('notifications.0.target', 'Thriller Thursday')
            ->assertJsonPath('notifications.0.detail', '“Something slow-burn please.”')
            ->assertJsonPath('notifications.0.to', "/rooms/{$room->id}");
        // Lina's count only has the two messages Sam sent after hers.
        $this->as($lina)->getJson('/api/notifications')->assertJsonPath('notifications.0.text', 'sent 2 new messages in');

        $this->as($omar)->postJson("/api/rooms/{$room->id}/read", ['message_ids' => Message::pluck('id')->all()])->assertOk();
        $this->assertNotNull(Notification::where('user_id', $omar->id)->where('type', Notification::NEW_MESSAGES)->sole()->read_at);

        // After reading, the next message starts a new count in the same notification.
        $this->send($lina, $room, 'Deep Blue tonight?');
        $this->as($omar)->getJson('/api/notifications')
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.text', 'sent a message in')
            ->assertJsonPath('notifications.0.read', false);
    }

    public function test_mentions_notify_the_people_named_who_can_see_the_room(): void
    {
        [$lina, $omar, $outsider] = User::factory()->count(3)->create()->all();
        $room = $this->room([$lina, $omar], private: true);

        $this->send($lina, $room, "@{$omar->username} and @{$outsider->username}: Deep Blue tonight? Ask @{$lina->username}.");

        $this->assertSame([Notification::MENTION, Notification::NEW_MESSAGES], $this->feedTypes($omar));
        $this->assertSame([], $this->feedTypes($outsider));
        $this->assertSame([], $this->feedTypes($lina));
        // The mention pops up; the room's "new messages" catches up quietly.
        Event::assertDispatched(NotificationPushed::class, fn (NotificationPushed $event): bool => $event->userId === $omar->id && $event->notification->kind === Notification::MENTION && ! $event->silent);
        Event::assertDispatched(NotificationPushed::class, fn (NotificationPushed $event): bool => $event->userId === $omar->id && $event->notification->kind === Notification::NEW_MESSAGES && $event->silent);
    }

    public function test_movie_night_invites_answers_votes_and_changes_reach_the_right_people(): void
    {
        [$host, $guest, $decliner] = User::factory()->count(3)->create()->all();
        $nightId = $this->createNight($host, [$guest, $decliner]);

        $this->as($guest)->getJson('/api/notifications')
            ->assertJsonPath('notifications.0.kind', 'night_invite')
            ->assertJsonPath('notifications.0.text', 'invited you to')
            ->assertJsonPath('notifications.0.target', 'Friday Frights')
            ->assertJsonPath('notifications.0.to', "/movie-nights/{$nightId}");

        $this->as($decliner)->putJson("/api/movie-nights/{$nightId}/rsvp", ['status' => 'declined'])->assertOk();
        $this->as($guest)->putJson("/api/movie-nights/{$nightId}/rsvp", ['status' => 'maybe'])->assertOk();
        $this->as($guest)->putJson("/api/movie-nights/{$nightId}/rsvp", ['status' => 'accepted'])->assertOk();
        $this->as($guest)->putJson("/api/movie-nights/{$nightId}/vote", ['tmdb_id' => 1003])->assertOk();
        $this->as($guest)->putJson("/api/movie-nights/{$nightId}/vote", ['tmdb_id' => 1008])->assertOk();

        // One answer per guest and one vote, each brought up to date rather than repeated.
        $texts = collect($this->as($host)->getJson('/api/notifications')->json('notifications'))->mapWithKeys(fn (array $n): array => [$n['actor']['username'].' '.$n['kind'] => $n]);
        $this->assertCount(3, $texts);
        $this->assertSame('can’t make it to', $texts["{$decliner->username} night_rsvp"]['text']);
        $this->assertSame('is going to', $texts["{$guest->username} night_rsvp"]['text']);
        $this->assertSame(['tmdb_id' => 1008, 'media_type' => 'movie'], $texts["{$guest->username} night_vote"]['film']);

        $this->as($host)->patchJson("/api/movie-nights/{$nightId}", ['status' => 'confirmed', 'winning_tmdb_id' => 1003])->assertOk();
        $this->as($guest)->getJson('/api/notifications')
            ->assertJsonPath('notifications.0.kind', 'night_update')
            ->assertJsonPath('notifications.0.text', 'locked in the pick for')
            ->assertJsonPath('notifications.0.film', ['tmdb_id' => 1003, 'media_type' => 'movie']);

        $this->as($host)->patchJson("/api/movie-nights/{$nightId}", ['status' => 'pending', 'winning_tmdb_id' => null])->assertOk();
        $this->as($host)->patchJson("/api/movie-nights/{$nightId}", ['note' => 'Bring snacks.'])->assertOk();

        // The latest change replaced the unread one; notes don't notify; the decliner hears nothing more.
        $update = Notification::where('user_id', $guest->id)->where('type', Notification::NIGHT_UPDATE)->sole();
        $this->assertSame('reopened', $update->data['change']);
        $this->assertSame([Notification::NIGHT_INVITE], $this->feedTypes($decliner));
    }

    public function test_a_switched_off_type_is_never_stored(): void
    {
        [$host, $guest] = User::factory()->count(2)->create()->all();
        $this->as($guest)->patchJson('/api/me/preferences', ['notifications' => ['night_invite' => false]])->assertOk();

        $this->createNight($host, [$guest]);

        $this->assertSame([], $this->feedTypes($guest));
    }

    public function test_marking_a_review_helpful_tells_its_author_and_taking_it_back_takes_it_away(): void
    {
        [$author, $reader] = User::factory()->count(2)->create()->all();
        $review = Review::create([
            'user_id' => $author->id, 'tmdb_id' => 550, 'media_type' => 'movie', 'rating' => 5,
            'content' => 'Sharp and strange.', 'sentiment' => 'positive', 'is_approved' => true,
        ]);

        $this->as($reader)->postJson("/api/reviews/{$review->id}/helpful")->assertOk();
        $this->as($reader)->postJson("/api/reviews/{$review->id}/helpful")->assertOk();

        $this->as($author)->getJson('/api/notifications')
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.kind', 'review_helpful')
            ->assertJsonPath('notifications.0.text', 'found your review helpful')
            ->assertJsonPath('notifications.0.film', ['tmdb_id' => 550, 'media_type' => 'movie'])
            ->assertJsonPath('notifications.0.to', '/movies/550#reviews');

        $this->as($reader)->deleteJson("/api/reviews/{$review->id}/helpful")->assertOk();

        $this->assertSame([], $this->feedTypes($author));
    }
}
