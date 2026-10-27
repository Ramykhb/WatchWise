<?php

namespace App\Room\DTOs\Responses;

use App\Models\Message;
use App\Models\User;
use App\User\Resources\MemberResource;
use JsonSerializable;

/**
 * One message with its read receipts. `read_by` starts with the author, who has read their own message;
 * `unread_by` lists the members it is still waiting for. When that list is empty the message has been
 * seen by everyone, and it is deleted once its 24 hours are up.
 */
final readonly class RoomMessageResponse implements JsonSerializable
{
    /**
     * @param  list<User>  $readBy  the author first, then the members who have read it
     * @param  list<User>  $unreadBy  members who still have to read it
     */
    public function __construct(
        public Message $message,
        public array $readBy,
        public array $unreadBy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->message->id,
            'room_id' => $this->message->room_id,
            'user' => (new MemberResource($this->message->user))->resolve(),
            'content' => $this->message->content,
            'created_at' => $this->message->created_at?->toJSON(),
            'read_by' => array_map(fn (User $user): array => (new MemberResource($user))->resolve(), $this->readBy),
            'unread_by' => array_map(fn (User $user): array => (new MemberResource($user))->resolve(), $this->unreadBy),
        ];
    }
}
