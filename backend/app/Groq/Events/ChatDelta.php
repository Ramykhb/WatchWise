<?php

namespace App\Groq\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ChatDelta implements ShouldBroadcastNow
{
    public function __construct(
        private readonly int $userId,
        private readonly string $conversationId,
        private readonly string $requestId,
        private readonly string $delta,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("chat.{$this->userId}.{$this->conversationId}")];
    }

    public function broadcastAs(): string
    {
        return 'chat.delta';
    }

    public function broadcastConnections(): array
    {
        return ['reverb'];
    }

    public function broadcastWith(): array
    {
        return ['request_id' => $this->requestId, 'delta' => $this->delta];
    }
}
