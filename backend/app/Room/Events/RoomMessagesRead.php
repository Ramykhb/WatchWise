<?php

namespace App\Room\Events;

use App\Models\User;
use App\User\Resources\MemberResource;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** A member has read messages; the ones in `deleted_ids` have now been read by every member and are gone. */
class RoomMessagesRead implements ShouldBroadcastNow
{
    /**
     * @param  list<int>  $messageIds
     * @param  list<int>  $deletedIds
     */
    public function __construct(
        public readonly int $roomId,
        public readonly User $reader,
        public readonly array $messageIds,
        public readonly array $deletedIds,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('room.'.$this->roomId)];
    }

    public function broadcastAs(): string
    {
        return 'messages.read';
    }

    /**
     * @return list<string>
     */
    public function broadcastConnections(): array
    {
        return ['reverb'];
    }

    /**
     * @return array{reader: array<string, mixed>, message_ids: list<int>, deleted_ids: list<int>}
     */
    public function broadcastWith(): array
    {
        return [
            'reader' => (new MemberResource($this->reader))->resolve(),
            'message_ids' => $this->messageIds,
            'deleted_ids' => $this->deletedIds,
        ];
    }
}
