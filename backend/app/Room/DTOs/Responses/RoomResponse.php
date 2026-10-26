<?php

namespace App\Room\DTOs\Responses;

use App\Models\DiscussionRoom;
use App\User\Resources\MemberResource;
use JsonSerializable;

/** A room card in the Rooms list: its film, host, members, invitations and the viewer's unread count. */
final readonly class RoomResponse implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(private array $payload) {}

    /** Expects the creator, members, invitees and latestMessage.user relations and the messages count to be loaded. */
    public static function fromModel(DiscussionRoom $room, int $viewerId, int $unread): self
    {
        $last = $room->latestMessage;
        $invite = $room->invitees->firstWhere('id', $viewerId);
        // Whoever invited the viewer is a member, unless they have left since.
        $inviter = $invite ? $room->members->firstWhere('id', $invite->pivot->invited_by) : null;

        return new self([
            'id' => $room->id,
            'name' => $room->name,
            'tmdb_id' => $room->tmdb_id,
            'spoilers' => $room->spoilers,
            'is_private' => $room->is_private,
            'host' => $room->creator ? (new MemberResource($room->creator))->resolve() : null,
            'members' => MemberResource::collection($room->members)->resolve(),
            'joined' => $room->members->contains('id', $viewerId),
            'invited' => $invite !== null,
            'invited_by' => $inviter ? (new MemberResource($inviter))->resolve() : null,
            'invitees' => MemberResource::collection($room->invitees)->resolve(),
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

    public function invited(): bool
    {
        return $this->payload['invited'];
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
