<?php

namespace App\Review\Repository;

use App\Models\Review;
use App\Models\ReviewReport;
use App\Models\ReviewVote;
use App\Models\User;
use App\Review\DTOs\Requests\MovieReviewQuery;
use App\Review\DTOs\Requests\SaveReviewData;
use App\Review\InterfaceRepository\MovieReviewRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MovieReviewRepository implements MovieReviewRepositoryInterface
{
    public function page(MovieReviewQuery $query, User $viewer): Collection
    {
        $reviews = $this->forTitle($query->tmdbId, $query->mediaType)
            ->with('user')
            ->withText()
            ->where('is_approved', true)
            ->where('user_id', '!=', $viewer->id)
            ->whereHas('user', fn (Builder $authors) => $this->visibleAuthors($authors, $viewer))
            ->withCount('votes')
            ->withExists(['votes as voted' => fn (Builder $votes) => $votes->where('user_id', $viewer->id)]);

        match ($query->sort) {
            'newest' => $reviews->latest()->orderByDesc('id'),
            'highest' => $reviews->orderByDesc('rating')->latest(),
            'lowest' => $reviews->orderBy('rating')->latest(),
            default => $reviews->orderByDesc('votes_count')->latest(),
        };

        return $reviews
            ->skip(($query->page - 1) * MovieReviewQuery::PER_PAGE)
            ->take(MovieReviewQuery::PER_PAGE + 1)
            ->get();
    }

    public function mine(User $user, int $tmdbId, string $mediaType): ?Review
    {
        return $this->forTitle($tmdbId, $mediaType)
            ->with('user')
            ->withText()
            ->where('user_id', $user->id)
            ->withCount('votes')
            ->first();
    }

    public function summary(int $tmdbId, string $mediaType): array
    {
        $stars = $this->forTitle($tmdbId, $mediaType)
            ->where('is_approved', true)
            ->selectRaw('rating, count(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating')
            ->map(fn ($total): int => (int) $total);

        $sentiment = $this->forTitle($tmdbId, $mediaType)
            ->withText()
            ->where('is_approved', true)
            ->whereNotNull('sentiment')
            ->selectRaw('sentiment, count(*) as total')
            ->groupBy('sentiment')
            ->pluck('total', 'sentiment')
            ->map(fn ($total): int => (int) $total);

        $ratings = $stars->sum();
        $total = $stars->reduce(fn (int $sum, int $count, $rating): int => $sum + $count * (int) $rating, 0);

        return [
            'average' => $ratings > 0 ? round($total / $ratings, 1) : null,
            'ratings' => $ratings,
            'stars' => $stars->all(),
            'reviews' => $sentiment->sum(),
            'sentiment' => $sentiment->all(),
        ];
    }

    public function authorVisibleTo(Review $review, User $viewer): bool
    {
        $author = User::query()->whereKey($review->user_id);
        $this->visibleAuthors($author, $viewer);

        return $author->exists();
    }

    public function save(User $user, SaveReviewData $data, string $sentiment): Review
    {
        return DB::transaction(fn (): Review => $this->write($user, $data, $sentiment));
    }

    private function write(User $user, SaveReviewData $data, string $sentiment): Review
    {
        // Votes belong to the text they were given for: new or resubmitted text starts from zero.
        $existing = $this->forTitle($data->tmdbId, $data->mediaType)->where('user_id', $user->id)->first();
        $existing?->votes()->delete();
        $existing?->reports()->delete();

        $review = Review::updateOrCreate(
            ['user_id' => $user->id, 'tmdb_id' => $data->tmdbId, 'media_type' => $data->mediaType],
            [
                'content' => $data->content,
                'rating' => $data->rating,
                'sentiment' => $sentiment,
                'has_spoilers' => $data->hasSpoilers,
                // New or edited text waits for an administrator again.
                'is_approved' => false,
                'removed_at' => null,
            ],
        );

        return $review->load('user')->loadCount('votes');
    }

    public function clearText(User $user, int $tmdbId, string $mediaType): void
    {
        $review = $this->forTitle($tmdbId, $mediaType)->where('user_id', $user->id)->first();
        if ($review === null) {
            return;
        }

        $review->votes()->delete();
        // A rating with no text has nothing to moderate.
        $review->update(['content' => null, 'sentiment' => null, 'has_spoilers' => false, 'is_approved' => true]);
    }

    public function saveReport(Review $review, User $reporter, string $reason): bool
    {
        $report = ReviewReport::updateOrCreate(['review_id' => $review->id, 'user_id' => $reporter->id], ['reason' => $reason]);

        return $report->wasRecentlyCreated;
    }

    public function vote(Review $review, User $user): void
    {
        ReviewVote::insertOrIgnore(['review_id' => $review->id, 'user_id' => $user->id, 'created_at' => now()]);
    }

    public function unvote(Review $review, User $user): void
    {
        $review->votes()->where('user_id', $user->id)->delete();
    }

    public function votesCount(Review $review): int
    {
        return $review->votes()->count();
    }

    /**
     * @return Builder<Review>
     */
    private function forTitle(int $tmdbId, string $mediaType): Builder
    {
        return Review::query()->where('tmdb_id', $tmdbId)->where('media_type', $mediaType);
    }

    /**
     * Authors the viewer may read: active members whose profile is public (or followers-only and
     * followed by the viewer) and who haven't turned off "Show reviews you write" in Settings.
     *
     * @param  Builder<User>  $authors
     */
    private function visibleAuthors(Builder $authors, User $viewer): void
    {
        $authors->where('is_active', true)
            ->where(fn (Builder $visible) => $visible
                ->where('profile_visibility', 'public')
                ->orWhere(fn (Builder $followersOnly) => $followersOnly
                    ->where('profile_visibility', 'followers')
                    ->whereHas('followers', fn (Builder $followers) => $followers->whereKey($viewer->id))))
            ->whereDoesntHave('preferences', fn (Builder $preferences) => $preferences->where('settings->privacy->reviews', false));
    }
}
