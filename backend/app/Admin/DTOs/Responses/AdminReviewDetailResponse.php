<?php

namespace App\Admin\DTOs\Responses;

use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use JsonSerializable;

/**
 * One review in full for staff: everything its queue row has, its whole text, every report filed against it
 * (open and closed) and what is known about its author.
 */
final readonly class AdminReviewDetailResponse implements JsonSerializable
{
    /**
     * @param  array{reviews_with_text: int, removed_reviews: int, open_reports: int}  $authorActivity
     * @param  list<array<string, mixed>>  $reports  oldest first
     */
    public function __construct(
        public AdminReviewResponse $row,
        public ?string $removedAt,
        public int $helpfulVotes,
        public ?User $author,
        public array $authorActivity,
        public array $reports,
    ) {}

    /**
     * Needs the review's `user` and its `reports` with their `reporter` and `resolver` loaded.
     *
     * @param  array{helpful_votes: int, author: array{reviews_with_text: int, removed_reviews: int, open_reports: int}}  $context
     */
    public static function fromModel(AdminReviewResponse $row, Review $review, array $context): self
    {
        return new self(
            $row,
            $review->removed_at?->toJSON(),
            $context['helpful_votes'],
            $review->user,
            $context['author'],
            $review->reports->sortBy('id')->values()->map(fn (Report $report): array => [
                'id' => $report->id,
                'status' => $report->status->value,
                'reason' => $report->reason,
                'details' => $report->details,
                'reporter' => self::member($report->reporter),
                'resolved_by' => self::member($report->resolver),
                'resolution_note' => $report->resolution_note,
                'created_at' => $report->created_at?->toJSON(),
                'resolved_at' => $report->resolved_at?->toJSON(),
            ])->all(),
        );
    }

    /**
     * @return array{id: int, name: string, username: ?string}|null
     */
    private static function member(?User $user): ?array
    {
        return $user === null ? null : ['id' => $user->id, 'name' => $user->name, 'username' => $user->username];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            ...$this->row->jsonSerialize(),
            'removed_at' => $this->removedAt,
            'helpful_votes' => $this->helpfulVotes,
            'author' => [
                'id' => $this->author?->id,
                'name' => $this->author?->name,
                'username' => $this->author?->username,
                'role' => $this->author?->role,
                'is_active' => $this->author === null ? null : (bool) $this->author->is_active,
                'reviews_with_text' => $this->authorActivity['reviews_with_text'],
                'removed_reviews' => $this->authorActivity['removed_reviews'],
                'open_reports_against_their_reviews' => $this->authorActivity['open_reports'],
            ],
            'reports' => $this->reports,
        ];
    }
}
