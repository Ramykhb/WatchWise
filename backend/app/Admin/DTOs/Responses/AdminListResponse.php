<?php

namespace App\Admin\DTOs\Responses;

use App\Models\MovieList;
use JsonSerializable;

/** A member's list in the staff table: whatever its visibility, with its owner and how many open reports it has. */
final readonly class AdminListResponse implements JsonSerializable
{
    /** Longest part of the description a table row shows. */
    private const EXCERPT_LENGTH = 200;

    /**
     * @param  'public'|'private'  $visibility
     * @param  array{id: int, name: string, username: ?string}|null  $owner
     */
    public function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        public string $visibility,
        public ?array $owner,
        public int $itemsCount,
        public int $openReportsCount,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /** Needs the list's `user` loaded and `items_count`. The description is cut to an excerpt unless `$fullDescription`. */
    public static function fromModel(MovieList $list, int $openReportsCount, bool $fullDescription = false): self
    {
        return new self(
            id: $list->id,
            title: $list->title,
            description: $fullDescription || $list->description === null ? $list->description : mb_substr($list->description, 0, self::EXCERPT_LENGTH),
            visibility: $list->is_public ? 'public' : 'private',
            owner: $list->user === null ? null : ['id' => $list->user->id, 'name' => $list->user->name, 'username' => $list->user->username],
            itemsCount: (int) $list->items_count,
            openReportsCount: $openReportsCount,
            createdAt: $list->created_at?->toJSON(),
            updatedAt: $list->updated_at?->toJSON(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'visibility' => $this->visibility,
            'owner' => $this->owner,
            'items_count' => $this->itemsCount,
            'open_reports_count' => $this->openReportsCount,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
