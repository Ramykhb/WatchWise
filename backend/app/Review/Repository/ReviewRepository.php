<?php

namespace App\Review\Repository;

use App\Models\Review;
use App\Review\InterfaceRepository\ReviewRepositoryInterface;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ReviewRepository implements ReviewRepositoryInterface
{
    public function paginateForModeration(string $status, int $page, int $perPage, string $search = '', ?string $sentiment = null, ?string $reason = null): LengthAwarePaginator
    {
        $reviews = Review::query()->with(['user', 'reports']);

        match ($status) {
            'removed' => $reviews->whereNotNull('removed_at'),
            'flagged' => $this->flagged($reviews),
            'approved' => $this->standing($reviews)->where('is_approved', true)->whereDoesntHave('reports', $this->open(...)),
            default => $this->standing($reviews)->where('is_approved', false)->whereDoesntHave('reports', $this->open(...)),
        };

        return $reviews
            ->when($search !== '', function (Builder $builder) use ($search) {
                $pattern = LikePattern::contains(mb_strtolower($search));
                $builder->where(fn (Builder $match) => $match
                    ->whereRaw("LOWER(content) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereHas('user', fn (Builder $author) => $author
                        ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("LOWER(username) LIKE ? ESCAPE '!'", [$pattern])));
            })
            ->when($sentiment !== null, fn (Builder $builder) => $builder->where('sentiment', $sentiment))
            ->when($reason !== null, fn (Builder $builder) => $builder->whereHas('reports', fn (Builder $reports) => $reports->where('reason', $reason)))
            ->latest()
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function moderationTotals(): array
    {
        $sentiment = $this->standing(Review::query())
            ->whereNotNull('sentiment')
            ->selectRaw('sentiment, count(*) as total')
            ->groupBy('sentiment')
            ->pluck('total', 'sentiment');

        return [
            'flagged' => $this->flagged(Review::query())->count(),
            'removed' => Review::query()->whereNotNull('removed_at')->count(),
            'sentiment' => [
                'positive' => (int) ($sentiment['positive'] ?? 0),
                'neutral' => (int) ($sentiment['neutral'] ?? 0),
                'negative' => (int) ($sentiment['negative'] ?? 0),
            ],
        ];
    }

    public function markApproved(Review $review): Review
    {
        $review->update(['is_approved' => true]);
        $review->reports()->whereNull('resolved_at')->update(['resolved_at' => now()]);

        return $review;
    }

    public function clearText(Review $review): Review
    {
        // With no text left there is nothing to moderate, the same as a plain star rating.
        $review->update(['content' => null, 'sentiment' => null, 'has_spoilers' => false, 'is_approved' => true, 'removed_at' => now()]);
        $review->reports()->whereNull('resolved_at')->update(['resolved_at' => now()]);

        return $review;
    }

    public function delete(Review $review): void
    {
        $review->delete();
    }

    /**
     * Reviews with text that no moderator has removed.
     *
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    private function standing(Builder $query): Builder
    {
        return $query->whereNotNull('content')->where('content', '!=', '')->whereNull('removed_at');
    }

    /**
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    private function flagged(Builder $query): Builder
    {
        return $this->standing($query)->whereHas('reports', $this->open(...));
    }

    /**
     * @param  Builder<*>  $reports
     */
    private function open(Builder $reports): void
    {
        $reports->whereNull('resolved_at');
    }
}
