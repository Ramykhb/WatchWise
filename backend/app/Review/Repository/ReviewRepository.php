<?php

namespace App\Review\Repository;

use App\Models\Review;
use App\Review\InterfaceRepository\ReviewRepositoryInterface;
use App\Support\LikePattern;
use Illuminate\Pagination\LengthAwarePaginator;

class ReviewRepository implements ReviewRepositoryInterface
{
    public function paginateWithText(string $status, int $page, int $perPage, string $search = '', ?string $sentiment = null, ?string $reason = null): LengthAwarePaginator
    {
        return Review::query()
            ->with('user')
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->where('is_approved', $status === 'approved')
            ->when($search !== '', function ($builder) use ($search) {
                $pattern = LikePattern::contains(mb_strtolower($search));
                $builder->where(fn ($match) => $match
                    ->whereRaw("LOWER(content) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereHas('user', fn ($author) => $author
                        ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("LOWER(username) LIKE ? ESCAPE '!'", [$pattern])));
            })
            ->when($sentiment !== null, fn ($builder) => $builder->where('sentiment', $sentiment))
            ->when($reason !== null, function ($builder) use ($reason) {
                $reason === 'awaiting classification'
                    ? $builder->whereNull('moderation_labels')
                    : $builder->whereJsonContains('moderation_labels', $reason);
            })
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
        $review->votes()->delete();
        $review->update(['content' => null, 'sentiment' => null, 'moderation_labels' => null, 'has_spoilers' => false, 'is_approved' => true]);

        return $review;
    }

    public function delete(Review $review): void
    {
        $review->delete();
    }
}
