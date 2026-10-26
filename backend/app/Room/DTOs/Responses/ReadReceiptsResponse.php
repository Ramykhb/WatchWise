<?php

namespace App\Room\DTOs\Responses;

use JsonSerializable;

/** POST /api/rooms/{room}/read: what the reader has now read, and which of those everyone has (they are deleted). */
final readonly class ReadReceiptsResponse implements JsonSerializable
{
    /**
     * @param  list<int>  $messageIds  messages newly marked read by the reader
     * @param  list<int>  $deletedIds  of those, the ones every member has now read
     */
    public function __construct(
        public array $messageIds,
        public array $deletedIds,
    ) {}

    /**
     * @return array{message_ids: list<int>, deleted_ids: list<int>}
     */
    public function jsonSerialize(): array
    {
        return [
            'message_ids' => $this->messageIds,
            'deleted_ids' => $this->deletedIds,
        ];
    }
}
