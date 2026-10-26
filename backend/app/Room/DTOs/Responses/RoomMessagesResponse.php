<?php

namespace App\Room\DTOs\Responses;

use JsonSerializable;

/** GET /api/rooms/{room}/messages: the messages someone still has to read, oldest first. */
final readonly class RoomMessagesResponse implements JsonSerializable
{
    /**
     * @param  list<RoomMessageResponse>  $messages
     */
    public function __construct(public array $messages) {}

    /**
     * @return array{messages: list<RoomMessageResponse>}
     */
    public function jsonSerialize(): array
    {
        return ['messages' => $this->messages];
    }
}
