<?php

namespace App\Review\Services;

use App\Models\Review;
use App\Models\User;
use App\Report\InterfaceServices\ReportServiceInterface;
use App\Report\Support\ReportTargetType;
use App\Review\InterfaceRepository\ReviewRepositoryInterface;
use App\Review\InterfaceServices\ReviewServiceInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ReviewService implements ReviewServiceInterface
{
    public function __construct(
        private readonly ReviewRepositoryInterface $reviews,
        private readonly ReportServiceInterface $reports,
    ) {}

    public function moderationQueue(string $status, int $page, int $perPage, string $search = '', ?string $sentiment = null, ?string $reason = null): LengthAwarePaginator
    {
        return $this->reviews->paginateForModeration($status, $page, $perPage, $search, $sentiment, $reason);
    }

    public function moderationTotals(): array
    {
        return $this->reviews->moderationTotals();
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

    public function delete(Review $review): void
    {
        $this->reviews->delete($review);
    }

    public function countsForUser(User $user): array
    {
        return $this->reviews->countsForUser($user);
    }
}
