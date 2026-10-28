<?php

namespace App\Review\Services;

use App\Models\Review;
use App\Models\User;
use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use App\Review\DTOs\Requests\MovieReviewQuery;
use App\Review\DTOs\Requests\ReportReviewData;
use App\Review\DTOs\Requests\SaveReviewData;
use App\Review\DTOs\Responses\MovieReviewResponse;
use App\Review\DTOs\Responses\ReportReviewResponse;
use App\Review\InterfaceRepository\MovieReviewRepositoryInterface;
use App\Review\InterfaceServices\MovieReviewServiceInterface;
use App\Support\MediaType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class MovieReviewService implements MovieReviewServiceInterface
{
    public function __construct(
        private readonly MovieReviewRepositoryInterface $reviews,
        private readonly SentimentClassifier $sentiment,
        private readonly MovieCatalogServiceInterface $catalog,
    ) {}

    public function forTitle(MovieReviewQuery $query, User $viewer): array
    {
        $page = $this->reviews->page($query, $viewer);
        $mine = $this->reviews->mine($viewer, $query->tmdbId, $query->mediaType);

        return [
            'summary' => $this->summary($query->tmdbId, $query->mediaType),
            'mine' => $mine ? new MovieReviewResponse($mine) : null,
            'reviews' => $page->take(MovieReviewQuery::PER_PAGE)->map(fn (Review $review) => new MovieReviewResponse($review))->values()->all(),
            'has_more' => $page->count() > MovieReviewQuery::PER_PAGE,
        ];
    }

    public function save(User $user, SaveReviewData $data): MovieReviewResponse
    {
        $review = $this->reviews->save($user, $data, $this->sentiment->classify($data->content, $data->rating));
        // The local catalogue (admin "Movie data") only holds movies.
        if ($data->mediaType === MediaType::MOVIE) {
            $this->catalog->remember($data->tmdbId);
        }

        return new MovieReviewResponse($review);
    }

    public function delete(User $user, int $tmdbId, string $mediaType): void
    {
        $this->reviews->clearText($user, $tmdbId, $mediaType);
    }

    public function report(Review $review, User $reporter, ReportReviewData $data): ReportReviewResponse
    {
        // A review the reporter can't read answers like one the policy refuses, so the response reveals nothing about it.
        if (! $this->reviews->authorVisibleTo($review, $reporter)) {
            throw new AuthorizationException;
        }

        return new ReportReviewResponse($this->reviews->saveReport($review, $reporter, $data->reason), $data->reason);
    }

    public function markHelpful(Review $review, User $user, bool $helpful): int
    {
        // Taking back your own vote is always allowed: it only removes your vote.
        if (! $helpful) {
            $this->reviews->unvote($review, $user);

            return $this->reviews->votesCount($review);
        }

        if ($review->user_id === $user->id) {
            throw ValidationException::withMessages(['review' => 'You can’t mark your own review as helpful.']);
        }
        // Only reviews the voter can read: approved text by an author whose privacy shows it to them.
        // A hidden review gets the same answer as an unapproved one, so the response reveals nothing about it.
        $readable = $review->is_approved
            && $review->content !== null
            && $review->content !== ''
            && $this->reviews->authorVisibleTo($review, $user);
        if (! $readable) {
            throw ValidationException::withMessages(['review' => 'This review can’t be voted on.']);
        }

        $this->reviews->vote($review, $user);

        return $this->reviews->votesCount($review);
    }

    /**
     * Counts turned into the percentages the page draws.
     *
     * @return array{average: float|null, ratings: int, reviews: int, distribution: list<int>, sentiment: array{positive: int, neutral: int, negative: int}}
     */
    private function summary(int $tmdbId, string $mediaType): array
    {
        $counts = $this->reviews->summary($tmdbId, $mediaType);
        $percent = fn (int $part, int $whole): int => $whole > 0 ? (int) round($part / $whole * 100) : 0;

        return [
            'average' => $counts['average'],
            'ratings' => $counts['ratings'],
            'reviews' => $counts['reviews'],
            // 5 stars first, like the page's bars.
            'distribution' => array_map(fn (int $stars): int => $percent($counts['stars'][$stars] ?? 0, $counts['ratings']), [5, 4, 3, 2, 1]),
            'sentiment' => [
                'positive' => $percent($counts['sentiment']['positive'] ?? 0, $counts['reviews']),
                'neutral' => $percent($counts['sentiment']['neutral'] ?? 0, $counts['reviews']),
                'negative' => $percent($counts['sentiment']['negative'] ?? 0, $counts['reviews']),
            ],
        ];
    }
}
