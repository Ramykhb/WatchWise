<?php

namespace App\Groq\DTOs\Responses;

use JsonSerializable;

final readonly class ChatResponse implements JsonSerializable
{
    public function __construct(public string $requestId, public string $message) {}

    public function jsonSerialize(): array
    {
        return ['request_id' => $this->requestId, 'message' => $this->message];
    }
}
