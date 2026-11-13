<?php

namespace App\Review\Services;

use App\Models\Review;
use App\Models\User;
use App\Report\InterfaceServices\ReportServiceInterface;
use App\Report\Support\ReportTargetType;
use App\Review\DTOs\Requests\ModerationQueueQuery;
use App\Review\Exceptions\ReviewHasNoText;
use App\Review\InterfaceRepository\ReviewRepositoryInterface;
use App\Review\InterfaceServices\ReviewServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ReviewService implements ReviewServiceInterface
{
    public function __construct(
        private readonly ReviewRepositoryInterface $reviews,
        private readonly ReportServiceInterface $reports,
    ) {}

    public function moderationQueue(ModerationQueueQuery $query): LengthAwarePaginator
    {
        return $this->reviews->paginateForModeration($query);
    }

    public function moderationTotals(): array
    {
        return $this->reviews->moderationTotals();
    }

    public function findMany(array $ids): Collection
    {
        return $this->reviews->findMany($ids);
    }

    public function moderationContext(Review $review): array
    {
        return [
            'helpful_votes' => $this->reviews->helpfulVotesCount($review),
            'author' => $this->reviews->authorModerationActivity((int) $review->user_id),
        ];
    }

    public function lockForUpdate(Review $review): Review
    {
        return $this->reviews->lockForUpdate($review);
    }

    public function openReportsCount(Review $review): int
    {
        return $this->reviews->openReportsCount($review);
    }

    public function approve(Review $review, User $moderator): Review
    {
        $approved = $this->reviews->markApproved($review);
        $this->reports->resolveForTarget(ReportTargetType::Review, $review->id, $moderator, 'dismissed');

        return $approved;
    }

    public function reject(Review $review, User $moderator): Review
    {
        $rejected = $this->reviews->clearText($review);
        $this->reports->resolveForTarget(ReportTargetType::Review, $review->id, $moderator, 'resolved');

        return $rejected;
    }

    public function changeSpoilers(Review $review, bool $hasSpoilers): Review
    {
        if ($review->removed_at !== null || blank($review->content)) {
            return $hasSpoilers ? throw new ReviewHasNoText : $review;
        }

        return $this->reviews->setSpoilers($review, $hasSpoilers);
    }

    public function delete(Review $review): void
    {
        $this->reviews->delete($review);
    }

    public function countsForUser(User $user): array
    {
        return $this->reviews->countsForUser($user);
    }
}
