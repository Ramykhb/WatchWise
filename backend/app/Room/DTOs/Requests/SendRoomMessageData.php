<?php

namespace App\Room\DTOs\Requests;

final readonly class SendRoomMessageData
{
    public const MAX_CHARS = 1000;

    public function __construct(public string $content) {}

    /**
     * @param  array{content: string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['content']);
    }
}
