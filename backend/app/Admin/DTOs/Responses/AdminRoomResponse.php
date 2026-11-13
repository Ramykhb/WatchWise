<?php

namespace App\Admin\DTOs\Responses;

use App\Models\DiscussionRoom;
use JsonSerializable;

/** A discussion room in the staff list: its film, creator, size, lock and how many open reports it has. */
final readonly class AdminRoomResponse implements JsonSerializable
{
    /**
     * @param  'movie'|'tv'  $mediaType
     * @param  array{id: int, name: string, username: string|null}|null  $creator
     */
    public function __construct(
        public int $id,
        public string $name,
        public int $tmdbId,
        public string $mediaType,
        public ?string $film,
        public bool $isPrivate,
        public bool $isLocked,
        public bool $spoilers,
        public ?array $creator,
        public int $membersCount,
        public int $messagesCount,
        public ?string $lastActivityAt,
        public int $openReportsCount,
        public int $openMessageReportsCount,
        public ?string $createdAt,
    ) {}

    /**
     * Needs the room's `creator` loaded, its `members_count`, `messages_count` and `last_message_at` selected.
     * `$film` is the title the local catalogue has (only movies are in it); `$openReports` counts the open reports on
     * the room itself and `$openMessageReports` those on its messages.
     */
    public static function fromModel(DiscussionRoom $room, ?string $film, int $openReports, int $openMessageReports): self
    {
        return new self(
            id: $room->id,
            name: $room->name,
            tmdbId: (int) $room->tmdb_id,
            mediaType: (string) $room->media_type,
            film: $film,
            isPrivate: (bool) $room->is_private,
            isLocked: (bool) $room->is_locked,
            spoilers: (bool) $room->spoilers,
            creator: $room->creator === null ? null : ['id' => $room->creator->id, 'name' => $room->creator->name, 'username' => $room->creator->username],
            membersCount: (int) $room->members_count,
            messagesCount: (int) $room->messages_count,
            lastActivityAt: ($room->last_message_at ?? $room->created_at)?->toJSON(),
            openReportsCount: $openReports,
            openMessageReportsCount: $openMessageReports,
            createdAt: $room->created_at?->toJSON(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tmdb_id' => $this->tmdbId,
            'media_type' => $this->mediaType,
            'film' => $this->film,
            'is_private' => $this->isPrivate,
            'is_locked' => $this->isLocked,
            'spoilers' => $this->spoilers,
            'creator' => $this->creator,
            'members_count' => $this->membersCount,
            'messages_count' => $this->messagesCount,
            'last_activity_at' => $this->lastActivityAt,
            'open_reports_count' => $this->openReportsCount,
            'open_message_reports_count' => $this->openMessageReportsCount,
            'created_at' => $this->createdAt,
        ];
    }
}
