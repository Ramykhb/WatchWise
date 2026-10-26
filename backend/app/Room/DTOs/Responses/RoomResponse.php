<?php

namespace App\Room\DTOs\Responses;

use App\Models\DiscussionRoom;
use App\User\Resources\MemberResource;
use JsonSerializable;

/** A room card in the Rooms list: its film, host, members and the viewer's unread count. */
final readonly class RoomResponse implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(private array $payload) {}

    /** Expects the creator, members and latestMessage.user relations and the messages count to be loaded. */
    public static function fromModel(DiscussionRoom $room, int $viewerId, int $unread): self
    {
        $last = $room->latestMessage;

        return new self([
            'id' => $room->id,
            'name' => $room->name,
            'tmdb_id' => $room->tmdb_id,
            'spoilers' => $room->spoilers,
            'host' => $room->creator ? (new MemberResource($room->creator))->resolve() : null,
            'members' => MemberResource::collection($room->members)->resolve(),
            'joined' => $room->members->contains('id', $viewerId),
            // Only messages someone still has to read are stored, so these describe the pending ones.
            'message_count' => (int) $room->messages_count,
            'unread' => $unread,
            'last_message' => $last ? [
                'author' => $last->user?->name,
                'content' => $last->content,
                'created_at' => $last->created_at?->toJSON(),
            ] : null,
            'created_at' => $room->created_at?->toJSON(),
        ]);
    }

    public function joined(): bool
    {
        return $this->payload['joined'];
    }

    /** When the room last had activity: its newest stored message, else its creation. */
    public function lastActivity(): string
    {
        return $this->payload['last_message']['created_at'] ?? $this->payload['created_at'] ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->payload;
    }
}
