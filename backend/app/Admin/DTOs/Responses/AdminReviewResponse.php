<?php

namespace App\Admin\DTOs\Responses;

use App\Models\Review;
use JsonSerializable;

/** A review in the moderation queue. */
final readonly class AdminReviewResponse implements JsonSerializable
{
    public function __construct(
        public int $id,
        public int $tmdbId,
        public ?string $content,
        public int $rating,
        public ?string $sentiment,
        public bool $isApproved,
        public ?int $authorId,
        public ?string $authorName,
        public ?string $authorUsername,
        public ?string $createdAt,
    ) {}

    public static function fromModel(Review $review): self
    {
        return new self(
            id: $review->id,
            tmdbId: (int) $review->tmdb_id,
            content: $review->content,
            rating: (int) $review->rating,
            sentiment: $review->sentiment,
            isApproved: (bool) $review->is_approved,
            authorId: $review->user?->id,
            authorName: $review->user?->name,
            authorUsername: $review->user?->username,
            createdAt: $review->created_at?->toJSON(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'tmdb_id' => $this->tmdbId,
            'content' => $this->content,
            'rating' => $this->rating,
            'sentiment' => $this->sentiment,
            'is_approved' => $this->isApproved,
            'author' => ['id' => $this->authorId, 'name' => $this->authorName, 'username' => $this->authorUsername],
            'created_at' => $this->createdAt,
        ];
    }
}
