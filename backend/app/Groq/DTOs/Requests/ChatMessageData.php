<?php

namespace App\Groq\DTOs\Requests;

final readonly class ChatMessageData
{
    public const MAX_MESSAGE_CHARS = 2000;

    public const MAX_HISTORY_MESSAGES = 20;

    public const MAX_HISTORY_CHARS = 24000;

    /** @param list<array{role: 'user'|'assistant', content: string}> $history */
    public function __construct(
        public string $message,
        public array $history,
        public string $conversationId,
        public string $requestId,
    ) {}

    /** @param array{message: string, history?: array, conversation_id: string, request_id: string} $data */
    public static function fromValidated(array $data): self
    {
        return new self($data['message'], $data['history'] ?? [], $data['conversation_id'], $data['request_id']);
    }
}
