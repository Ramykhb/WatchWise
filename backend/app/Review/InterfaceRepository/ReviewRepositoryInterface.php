<?php

namespace App\Review\InterfaceRepository;

use App\Models\Review;
use Illuminate\Pagination\LengthAwarePaginator;

interface ReviewRepositoryInterface
{
    /**
     * Reviews that have text (rating-only rows have nothing to moderate), newest first, with their author.
     *
     * @param  'pending'|'approved'  $status
     * @return LengthAwarePaginator<int, Review>
     */
    public function paginateWithText(string $status, int $page, int $perPage, string $search = '', ?string $sentiment = null, ?string $reason = null): LengthAwarePaginator;

    public function markApproved(Review $review): Review;

    /** Removes the text and sentiment but keeps the star rating. */
    public function clearText(Review $review): Review;

    public function delete(Review $review): void;
}
