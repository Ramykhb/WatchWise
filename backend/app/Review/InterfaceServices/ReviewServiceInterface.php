<?php

namespace App\Review\InterfaceServices;

use App\Models\Review;
use Illuminate\Pagination\LengthAwarePaginator;

interface ReviewServiceInterface
{
    /**
     * The reviews with text an administrator can moderate.
     *
     * @param  'pending'|'approved'  $status
     * @return LengthAwarePaginator<int, Review>
     */
    public function moderationQueue(string $status, int $page, int $perPage, string $search = '', ?string $sentiment = null, ?string $reason = null): LengthAwarePaginator;

    /** Makes the review visible to everyone. */
    public function approve(Review $review): Review;

    /** Rejects the text; the member's star rating stays. */
    public function reject(Review $review): Review;

    public function delete(Review $review): void;
}
