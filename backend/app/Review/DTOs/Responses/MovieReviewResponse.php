<?php

namespace App\Review\DTOs\Responses;

use App\Models\Review;
use App\User\Resources\MemberResource;
use JsonSerializable;

/** One review card on the Movie page. */
final readonly class MovieReviewResponse implements JsonSerializable
{
    public function __construct(private Review $review) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $review = $this->review;

        return [
            'id' => $review->id,
            'author' => (new MemberResource($review->user))->resolve(),
            'rating' => $review->rating,
            'sentiment' => $review->sentiment,
            'content' => $review->content,
            'has_spoilers' => (bool) $review->has_spoilers,
            'helpful' => (int) ($review->votes_count ?? 0),
            // Only set when the query asked (other members' reviews); your own review can't get your vote.
            'marked_helpful' => (bool) ($review->voted ?? false),
            // Your own review shows "Waiting for approval" until an administrator approves it.
            'approved' => (bool) $review->is_approved,
            'created_at' => $review->created_at?->toJSON(),
            'updated_at' => $review->updated_at?->toJSON(),
        ];
    }
}
