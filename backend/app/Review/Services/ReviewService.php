<?php

namespace App\Review\Services;

use App\Models\Review;
use App\Review\InterfaceRepository\ReviewRepositoryInterface;
use App\Review\InterfaceServices\ReviewServiceInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ReviewService implements ReviewServiceInterface
{
    public function __construct(private readonly ReviewRepositoryInterface $reviews) {}

    public function moderationQueue(string $status, int $page, int $perPage): LengthAwarePaginator
    {
        return $this->reviews->paginateForModeration($status, $page, $perPage);
    }

    public function moderationTotals(): array
    {
        return $this->reviews->moderationTotals();
    }

    public function approve(Review $review): Review
    {
        return $this->reviews->markApproved($review);
    }

    public function reject(Review $review): Review
    {
        return $this->reviews->clearText($review);
    }

    public function delete(Review $review): void
    {
        $this->reviews->delete($review);
    }
}
