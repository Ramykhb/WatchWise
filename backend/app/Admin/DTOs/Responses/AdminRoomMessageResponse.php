<?php

namespace App\Admin\DTOs\Responses;

use App\Models\Message;
use JsonSerializable;

/** A stored room message as staff read it, with how many open reports it has. */
final readonly class AdminRoomMessageResponse implements JsonSerializable
{
    /**
     * @param  array{id: int, name: string, username: string|null}|null  $author
     */
    public function __construct(
        public int $id,
        public ?array $author,
        public string $body,
        public ?string $createdAt,
        public int $openReportsCount,
    ) {}

    /** Needs the message's `user` loaded. */
    public static function fromModel(Message $message, int $openReports): self
    {
        return new self(
            id: $message->id,
            author: $message->user === null ? null : ['id' => $message->user->id, 'name' => $message->user->name, 'username' => $message->user->username],
            body: $message->content,
            createdAt: $message->created_at?->toJSON(),
            openReportsCount: $openReports,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'author' => $this->author,
            'body' => $this->body,
            'created_at' => $this->createdAt,
            'open_reports_count' => $this->openReportsCount,
        ];
    }
}
