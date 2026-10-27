<?php

namespace App\Review\Repository;

use App\Models\Review;
use App\Review\InterfaceRepository\ReviewRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ReviewRepository implements ReviewRepositoryInterface
{
    public function paginateWithText(string $status, int $page, int $perPage): LengthAwarePaginator
    {
        return Review::query()
            ->with('user')
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->where('is_approved', $status === 'approved')
            ->latest()
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function markApproved(Review $review): Review
    {
        $review->update(['is_approved' => true]);

        return $review;
    }

    public function clearText(Review $review): Review
    {
        // With no text left there is nothing to moderate, the same as a plain star rating.
        $review->update(['content' => null, 'sentiment' => null, 'is_approved' => true]);

        return $review;
    }

    public function delete(Review $review): void
    {
        $review->delete();
    }
}
