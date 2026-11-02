<?php

namespace App\Notification\Services;

use App\Models\DiscussionRoom;
use App\Models\Message;
use App\Models\MovieNight;
use App\Models\Notification;
use App\Models\Review;
use App\Models\User;
use App\Notification\InterfaceRepository\NotificationRepositoryInterface;
use App\Notification\InterfaceServices\NotificationServiceInterface;
use Illuminate\Support\Str;

class NotificationService implements NotificationServiceInterface
{
    /** Longest quote of a message kept in a notification. */
    private const PREVIEW_LENGTH = 120;

    /** Recent senders a room's "new messages" remembers, enough for "and 2 others". */
    private const SENDERS_KEPT = 10;

    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
        private readonly LiveNotifications $live,
    ) {}

    public function followed(User $follower, User $target): void
    {
        $this->notifyOnce($target->id, Notification::FOLLOW, ['actor_id' => $follower->id], ['actor_id']);
    }

    public function requestFollow(User $requester, User $target): void
    {
        if ($this->followRequest($requester, $target) === null) {
            $this->live->push($this->notifications->create($target->id, Notification::FOLLOW_REQUEST, ['actor_id' => $requester->id]));
        }
    }

    public function hasRequestedFollow(User $requester, User $target): bool
    {
        return $this->followRequest($requester, $target) !== null;
    }

    public function followRequestedAmong(User $requester, array $userIds): array
    {
        return $this->notifications->requestedBy($requester->id, $userIds);
    }

    public function removeFollowRequest(User $requester, User $target): bool
    {
        $request = $this->followRequest($requester, $target);

        if ($request === null) {
            return false;
        }

        $this->notifications->delete([$request->id]);
        $this->live->changed($target->id);

        return true;
    }

    public function acceptFollowRequest(User $requester, User $target): void
    {
        $request = $this->followRequest($requester, $target);

        if ($request !== null) {
            // The request becomes "started following you", with its Follow back button, unless the target has
            // switched new-follower notifications off. Answering it reads it.
            if ($this->notifications->wanting([$target->id], Notification::FOLLOW) === []) {
                $this->notifications->delete([$request->id]);
                $this->live->changed($target->id);
            } else {
                $this->live->push($this->notifications->update($request, ['type' => Notification::FOLLOW, 'read_at' => $request->read_at ?? now()]), silent: true);
            }
        }

        $this->notify([$requester->id], Notification::FOLLOW_ACCEPTED, ['actor_id' => $target->id]);
    }

    public function roomInvited(DiscussionRoom $room, User $inviter, array $inviteeIds): void
    {
        $this->notify($inviteeIds, Notification::ROOM_INVITE, ['actor_id' => $inviter->id, ...$this->roomData($room)]);
    }

    public function roomInviteAccepted(DiscussionRoom $room, User $invitee, int $inviterId): void
    {
        $this->markRoomRead($room, $invitee->id, [Notification::ROOM_INVITE]);
        $this->notify([$inviterId], Notification::ROOM_JOINED, ['actor_id' => $invitee->id, ...$this->roomData($room)]);
    }

    public function roomInviteDeclined(DiscussionRoom $room, User $invitee): void
    {
        $invites = $this->notifications->matching([$invitee->id], [Notification::ROOM_INVITE], ['room_id' => $room->id]);

        if ($invites->isNotEmpty()) {
            $this->notifications->delete($invites->modelKeys());
            $this->live->changed($invitee->id);
        }
    }

    public function roomMessageSent(DiscussionRoom $room, Message $message, array $memberIds, array $mentionedIds): void
    {
        $senderId = (int) $message->user_id;
        $preview = Str::limit(Str::squish($message->content), self::PREVIEW_LENGTH, '…');

        $mentioned = $this->notifications->wanting(array_values(array_diff($mentionedIds, [$senderId])), Notification::MENTION);
        foreach ($mentioned as $userId) {
            $this->live->push($this->notifications->create($userId, Notification::MENTION, ['actor_id' => $senderId, ...$this->roomData($room), 'preview' => $preview]));
        }

        $members = $this->notifications->wanting(array_values(array_diff($memberIds, [$senderId])), Notification::NEW_MESSAGES);
        $current = $this->notifications->matching($members, [Notification::NEW_MESSAGES], ['room_id' => $room->id])
            ->unique('user_id')
            ->keyBy('user_id');

        foreach ($members as $userId) {
            $existing = $current->get($userId);
            // An unread one counts up; one already read starts again from this message.
            $counting = $existing !== null && $existing->read_at === null;
            $earlierSenders = $counting ? array_map('intval', (array) ($existing->data['sender_ids'] ?? [])) : [];
            $data = [
                'actor_id' => $senderId,
                ...$this->roomData($room),
                'count' => $counting ? (int) ($existing->data['count'] ?? 1) + 1 : 1,
                'sender_ids' => array_slice(array_values(array_unique([$senderId, ...$earlierSenders])), 0, self::SENDERS_KEPT),
                'preview' => $preview,
            ];

            $notification = $existing === null
                ? $this->notifications->create($userId, Notification::NEW_MESSAGES, $data)
                : $this->notifications->update($existing, ['data' => $data, 'read_at' => null, 'created_at' => now()]);

            // Someone mentioned in this message has just had a pop-up for it.
            $this->live->push($notification, silent: in_array($userId, $mentioned, true));
        }
    }

    public function roomRead(DiscussionRoom $room, User $reader): void
    {
        $this->markRoomRead($room, $reader->id, [Notification::NEW_MESSAGES, Notification::MENTION]);
    }

    public function roomDeleted(DiscussionRoom $room, User $deleter, array $audienceIds): void
    {
        // Mentions can reach people outside the room, so this looks at everyone's notifications.
        $stale = $this->notifications->matchingAnyone(
            [Notification::ROOM_INVITE, Notification::ROOM_JOINED, Notification::NEW_MESSAGES, Notification::MENTION],
            ['room_id' => $room->id],
        );

        if ($stale->isNotEmpty()) {
            $this->notifications->delete($stale->modelKeys());
            $stale->pluck('user_id')->unique()->each(fn ($userId) => $this->live->changed((int) $userId));
        }

        $this->notify($audienceIds, Notification::ROOM_DELETED, ['actor_id' => $deleter->id, 'room_name' => $room->name]);
    }

    public function nightInvited(MovieNight $night, User $host, array $guestIds): void
    {
        $this->notify($guestIds, Notification::NIGHT_INVITE, ['actor_id' => $host->id, ...$this->nightData($night)]);
    }

    public function nightAnswered(MovieNight $night, User $guest, string $status): void
    {
        $this->notifyOnce((int) $night->organizer_id, Notification::NIGHT_RSVP, ['actor_id' => $guest->id, ...$this->nightData($night), 'status' => $status], ['actor_id', 'night_id']);
    }

    public function nightVoted(MovieNight $night, User $voter, int $tmdbId): void
    {
        $this->notifyOnce((int) $night->organizer_id, Notification::NIGHT_VOTE, ['actor_id' => $voter->id, ...$this->nightData($night), 'tmdb_id' => $tmdbId], ['actor_id', 'night_id']);
    }

    public function nightChanged(MovieNight $night, string $change, array $guestIds): void
    {
        $data = [
            'actor_id' => (int) $night->organizer_id,
            ...$this->nightData($night),
            'change' => $change,
            ...($change === 'confirmed' && $night->winning_tmdb_id !== null ? ['tmdb_id' => (int) $night->winning_tmdb_id] : []),
        ];

        // Only the latest change of a night matters, so it replaces an unread earlier one.
        foreach ($guestIds as $guestId) {
            $this->notifyOnce((int) $guestId, Notification::NIGHT_UPDATE, $data, ['night_id']);
        }
    }

    public function reviewMarkedHelpful(Review $review, User $voter): void
    {
        $this->notify([(int) $review->user_id], Notification::REVIEW_HELPFUL, [
            'actor_id' => $voter->id,
            'review_id' => $review->id,
            'tmdb_id' => (int) $review->tmdb_id,
            'media_type' => $review->media_type ?? 'movie',
        ]);
    }

    public function reviewUnmarkedHelpful(Review $review, User $voter): void
    {
        $notifications = $this->notifications->matching([(int) $review->user_id], [Notification::REVIEW_HELPFUL], ['review_id' => $review->id, 'actor_id' => $voter->id]);

        if ($notifications->isNotEmpty()) {
            $this->notifications->delete($notifications->modelKeys());
            $this->live->changed((int) $review->user_id);
        }
    }

    /**
     * Stores the notification for each of `$userIds` who wants this type (never the actor) and pushes it.
     *
     * @param  list<int>  $userIds
     * @param  array<string, mixed>  $data  holds the actor_id
     */
    private function notify(array $userIds, string $type, array $data): void
    {
        $recipients = $this->notifications->wanting(array_values(array_diff($userIds, [$data['actor_id']])), $type);

        foreach ($recipients as $userId) {
            $this->live->push($this->notifications->create($userId, $type, $data));
        }
    }

    /**
     * Like notify(), but an unread notification about the same thing (the `$sameKeys` of `$data`) is brought up
     * to date and moved to the top instead of piling up another one.
     *
     * @param  array<string, mixed>  $data  holds the actor_id
     * @param  list<string>  $sameKeys
     */
    private function notifyOnce(int $userId, string $type, array $data, array $sameKeys): void
    {
        if ($userId === $data['actor_id'] || $this->notifications->wanting([$userId], $type) === []) {
            return;
        }

        $unread = $this->notifications->matching([$userId], [$type], array_intersect_key($data, array_flip($sameKeys)))
            ->first(fn (Notification $notification): bool => $notification->read_at === null);

        $this->live->push($unread === null
            ? $this->notifications->create($userId, $type, $data)
            : $this->notifications->update($unread, ['data' => $data, 'created_at' => now()]));
    }

    /**
     * @param  list<string>  $types
     */
    private function markRoomRead(DiscussionRoom $room, int $userId, array $types): void
    {
        $unread = $this->notifications->matching([$userId], $types, ['room_id' => $room->id])->whereNull('read_at');

        if ($unread->isNotEmpty()) {
            $this->notifications->setRead($unread->modelKeys(), true);
            $this->live->changed($userId);
        }
    }

    private function followRequest(User $requester, User $target): ?Notification
    {
        return $this->notifications->matching([$target->id], [Notification::FOLLOW_REQUEST], ['actor_id' => $requester->id])->first();
    }

    /**
     * @return array{room_id: int, room_name: string}
     */
    private function roomData(DiscussionRoom $room): array
    {
        return ['room_id' => $room->id, 'room_name' => $room->name];
    }

    /**
     * @return array{night_id: int, title: string}
     */
    private function nightData(MovieNight $night): array
    {
        return ['night_id' => $night->id, 'title' => $night->title];
    }
}
