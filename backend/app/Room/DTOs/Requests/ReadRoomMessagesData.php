<?php

namespace App\Room\DTOs\Requests;

final readonly class ReadRoomMessagesData
{
    /**
     * @param  list<int>  $messageIds  messages the reader has now seen
     */
    public function __construct(public array $messageIds) {}

    /**
     * @param  array{message_ids: list<int|string>}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(array_values(array_map(intval(...), $validated['message_ids'])));
    }
}
