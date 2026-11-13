<?php

namespace App\Admin\DTOs\Responses;

use App\Models\AdminUserNote;
use JsonSerializable;

/** One internal note about a member, as staff see it. The member never does. */
final readonly class AdminUserNoteResponse implements JsonSerializable
{
    /**
     * @param  array{id: int, name: string, username: string|null, role: string}|null  $author  null once the author's account is deleted
     */
    public function __construct(
        public int $id,
        public string $body,
        public ?array $author,
        public ?string $createdAt,
    ) {}

    /** Needs the note's `author` loaded. */
    public static function fromModel(AdminUserNote $note): self
    {
        return new self(
            id: $note->id,
            body: $note->body,
            author: $note->author === null ? null : [
                'id' => $note->author->id,
                'name' => $note->author->name,
                'username' => $note->author->username,
                'role' => $note->author->role,
            ],
            createdAt: $note->created_at?->toJSON(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => $this->author,
            'created_at' => $this->createdAt,
        ];
    }
}
