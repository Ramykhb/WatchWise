<?php

namespace App\Notification\DTOs\Responses;

use App\Models\Notification;
use App\Models\User;
use App\Notification\Support\ListRestriction;
use JsonSerializable;

/**
 * One notification as the bell and the Notifications page show it: who did it (`actor`, in bold), the sentence
 * after their name (`text`), the bold object of the sentence (`target`), an optional quote (`detail`) and the
 * film it is about (`film`, whose title the browser looks up), plus the page it opens (`to`).
 */
final readonly class NotificationResponse implements JsonSerializable
{
    /** Who a notice from the WatchWise team shows as, so it never names the staff member behind it. */
    private const TEAM = ['id' => 0, 'username' => 'watchwise', 'name' => 'WatchWise', 'avatar' => null];

    /**
     * @param  array{id: int, username: string, name: string, avatar: string|null}  $actor
     * @param  array{tmdb_id: int, media_type: string}|null  $film
     */
    public function __construct(
        public int $id,
        public string $kind,
        public array $actor,
        public bool $actorFollowed,
        public bool $actorRequested,
        public string $text,
        public ?string $target,
        public ?string $detail,
        public ?array $film,
        public string $to,
        public string $action,
        public bool $read,
        public string $createdAt,
    ) {}

    /**
     * Null when the notification can't be shown any more: its actor deleted their account, or it is a type
     * (or an older row) without the fields its sentence needs. A notice from staff has no actor and shows
     * as from the WatchWise team, and so does a `room_deleted` row without an `actor_id` (staff deleted the room).
     *
     * @param  bool  $actorFollowed  whether the reader follows the actor
     * @param  bool  $actorRequested  whether the reader has asked to follow the actor
     */
    public static function fromModel(Notification $notification, ?User $actor, bool $actorFollowed, bool $actorRequested): ?self
    {
        $fromTeam = in_array($notification->type, Notification::TEAM_TYPES, true)
            || ($notification->type === Notification::ROOM_DELETED && ! isset($notification->data['actor_id']));

        if ($actor === null && ! $fromTeam) {
            return null;
        }

        $data = $notification->data ?? [];
        $parts = match ($notification->type) {
            Notification::FOLLOW => self::profile($actor, 'started following you'),
            Notification::FOLLOW_REQUEST => self::profile($actor, 'asked to follow you'),
            Notification::FOLLOW_ACCEPTED => self::profile($actor, 'accepted your follow request'),
            Notification::ROOM_INVITE => self::room($data, 'invited you to the private room', 'Open room'),
            Notification::ROOM_JOINED => self::room($data, 'accepted your invite to', 'Open room'),
            // The room is gone, so it opens the Rooms page instead.
            Notification::ROOM_DELETED => ['text' => 'deleted the room', 'target' => (string) ($data['room_name'] ?? 'a room'), 'to' => '/rooms', 'action' => 'See rooms'],
            Notification::NEW_MESSAGES => self::room($data, self::messagesText($data), 'Reply', self::quote($data)),
            Notification::MENTION => self::room($data, 'mentioned you in', 'Reply', self::quote($data)),
            Notification::NIGHT_INVITE => self::night($data, 'invited you to', 'RSVP'),
            Notification::NIGHT_RSVP => self::night($data, self::rsvpText($data), 'View night'),
            Notification::NIGHT_VOTE => self::night($data, 'voted in', 'See the votes', self::film($data, 'movie')),
            Notification::NIGHT_UPDATE => self::night($data, self::updateText($data), 'View night', ($data['change'] ?? null) === 'confirmed' ? self::film($data, 'movie') : null),
            Notification::REVIEW_HELPFUL => self::review($data),
            Notification::REVIEW_REMOVED => self::removedReview($data),
            Notification::LIST_RESTRICTED => self::restrictedList($data),
            Notification::MESSAGE_REMOVED => self::staffRoomNotice($data, 'removed your message in'),
            Notification::ROOM_LOCKED => self::staffRoomNotice($data, 'locked your room'),
            Notification::ROOM_UNLOCKED => self::staffRoomNotice($data, 'unlocked your room'),
            Notification::ROOM_REMOVED => ['text' => 'deleted your room', 'target' => (string) ($data['room_name'] ?? 'a room'), 'film' => self::film($data, (string) ($data['media_type'] ?? 'movie')), 'to' => '/rooms', 'action' => 'See rooms'],
            default => null,
        };

        if ($parts === null) {
            return null;
        }

        return new self(
            id: $notification->id,
            kind: $notification->type,
            actor: $actor === null ? self::TEAM : self::member($actor, $actorFollowed),
            actorFollowed: $actorFollowed,
            actorRequested: $actorRequested,
            text: $parts['text'],
            target: $parts['target'] ?? null,
            detail: $parts['detail'] ?? null,
            film: $parts['film'] ?? null,
            to: $parts['to'],
            action: $parts['action'],
            read: $notification->read_at !== null,
            createdAt: $notification->created_at->toJSON(),
        );
    }

    /**
     * Only the avatar of a profile the reader may open is shown (the name and handle are public).
     *
     * @return array{id: int, username: string, name: string, avatar: string|null}
     */
    private static function member(User $actor, bool $followed): array
    {
        $avatarVisible = $actor->profile_visibility === 'public' || ($actor->profile_visibility === 'followers' && $followed);

        return ['id' => $actor->id, 'username' => $actor->username, 'name' => $actor->name, 'avatar' => $avatarVisible ? $actor->avatar : null];
    }

    /**
     * @return array{text: string, to: string, action: string}
     */
    private static function profile(User $actor, string $text): array
    {
        return ['text' => $text, 'to' => '/profile/'.$actor->username, 'action' => 'View profile'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{text: string, target: string, detail: string|null, to: string, action: string}|null
     */
    private static function room(array $data, string $text, string $action, ?string $detail = null): ?array
    {
        if (! isset($data['room_id'])) {
            return null;
        }

        return ['text' => $text, 'target' => (string) ($data['room_name'] ?? 'a room'), 'detail' => $detail, 'to' => '/rooms/'.(int) $data['room_id'], 'action' => $action];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{tmdb_id: int, media_type: string}|null  $film
     * @return array{text: string, target: string, film: array{tmdb_id: int, media_type: string}|null, to: string, action: string}|null
     */
    private static function night(array $data, string $text, string $action, ?array $film = null): ?array
    {
        if (! isset($data['night_id'])) {
            return null;
        }

        return ['text' => $text, 'target' => (string) ($data['title'] ?? 'a movie night'), 'film' => $film, 'to' => '/movie-nights/'.(int) $data['night_id'], 'action' => $action];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{text: string, film: array{tmdb_id: int, media_type: string}, to: string, action: string}|null
     */
    private static function review(array $data): ?array
    {
        $film = self::film($data, (string) ($data['media_type'] ?? 'movie'));
        if ($film === null) {
            return null;
        }
        $key = ($film['media_type'] === 'tv' ? 'tv-' : '').$film['tmdb_id'];

        return ['text' => 'found your review helpful', 'film' => $film, 'to' => "/movies/{$key}#reviews", 'action' => 'View review'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{text: string, film: array{tmdb_id: int, media_type: string}, to: string, action: string}|null
     */
    private static function removedReview(array $data): ?array
    {
        $film = self::film($data, (string) ($data['media_type'] ?? 'movie'));
        if ($film === null) {
            return null;
        }
        $key = ($film['media_type'] === 'tv' ? 'tv-' : '').$film['tmdb_id'];

        return ['text' => 'removed your review', 'film' => $film, 'to' => "/movies/{$key}", 'action' => 'View film'];
    }

    /**
     * A deleted list has no page, so its notice opens the Lists page.
     *
     * @param  array<string, mixed>  $data
     * @return array{text: string, target: string, film: array{tmdb_id: int, media_type: string}|null, to: string, action: string}|null
     */
    private static function restrictedList(array $data): ?array
    {
        $restriction = ListRestriction::tryFrom((string) ($data['action'] ?? ''));

        if ($restriction === null || ! isset($data['list_id'])) {
            return null;
        }

        $film = $restriction === ListRestriction::ItemRemoved ? self::film($data, (string) ($data['media_type'] ?? 'movie')) : null;
        $text = match ($restriction) {
            ListRestriction::MadePrivate => 'made your list private',
            ListRestriction::ItemRemoved => 'removed a film from your list',
            ListRestriction::Deleted => 'deleted your list',
        };

        return [
            'text' => $text,
            'target' => (string) ($data['list_title'] ?? 'a list'),
            'film' => $film,
            'to' => $restriction === ListRestriction::Deleted ? '/lists' : '/lists/'.(int) $data['list_id'],
            'action' => $restriction === ListRestriction::Deleted ? 'See lists' : 'View list',
        ];
    }

    /**
     * A staff action on a room or on a message in it: the room it happened in, with its film when the row has one.
     *
     * @param  array<string, mixed>  $data
     * @return array{text: string, target: string, detail: string|null, film: array{tmdb_id: int, media_type: string}|null, to: string, action: string}|null
     */
    private static function staffRoomNotice(array $data, string $text): ?array
    {
        $room = self::room($data, $text, 'Open room');

        return $room === null ? null : [...$room, 'film' => self::film($data, (string) ($data['media_type'] ?? 'movie'))];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{tmdb_id: int, media_type: string}|null
     */
    private static function film(array $data, string $mediaType): ?array
    {
        return isset($data['tmdb_id']) ? ['tmdb_id' => (int) $data['tmdb_id'], 'media_type' => $mediaType === 'tv' ? 'tv' : 'movie'] : null;
    }

    /**
     * "sent a message in", "sent 3 new messages in" or "and 2 others sent 5 new messages in".
     *
     * @param  array<string, mixed>  $data
     */
    private static function messagesText(array $data): string
    {
        $count = max(1, (int) ($data['count'] ?? 1));
        $others = max(0, count((array) ($data['sender_ids'] ?? [])) - 1);
        $sent = $count === 1 ? 'sent a message in' : "sent {$count} new messages in";

        return $others === 0 ? $sent : 'and '.$others.' '.($others === 1 ? 'other' : 'others').' '.$sent;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function rsvpText(array $data): string
    {
        return match ($data['status'] ?? null) {
            'accepted' => 'is going to',
            'maybe' => 'might come to',
            'declined' => 'can’t make it to',
            default => 'answered your invite to',
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function updateText(array $data): string
    {
        return match ($data['change'] ?? null) {
            'confirmed' => 'locked in the pick for',
            'cancelled' => 'cancelled',
            'reopened' => 'reopened voting for',
            'rescheduled' => 'changed the time of',
            default => 'updated',
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function quote(array $data): ?string
    {
        $preview = trim((string) ($data['preview'] ?? ''));

        return $preview === '' ? null : '“'.$preview.'”';
    }

    /**
     * @return array{id: int, kind: string, actor: array{id: int, username: string, name: string, avatar: string|null}, actor_followed: bool, actor_requested: bool, text: string, target: string|null, detail: string|null, film: array{tmdb_id: int, media_type: string}|null, to: string, action: string, read: bool, created_at: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'actor' => $this->actor,
            'actor_followed' => $this->actorFollowed,
            'actor_requested' => $this->actorRequested,
            'text' => $this->text,
            'target' => $this->target,
            'detail' => $this->detail,
            'film' => $this->film,
            'to' => $this->to,
            'action' => $this->action,
            'read' => $this->read,
            'created_at' => $this->createdAt,
        ];
    }
}
