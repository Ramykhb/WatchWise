<?php

namespace App\Admin\DTOs\Responses;

use App\Models\LocalMovie;
use App\Models\Review;
use App\Models\ReviewReport;
use JsonSerializable;

/** A review in the moderation queue, with its reports and the film it is about. */
final readonly class AdminReviewResponse implements JsonSerializable
{
    /**
     * @param  'flagged'|'approved'|'removed'|'pending'  $status
     * @param  list<string>  $reasons  the distinct reasons members gave when reporting it
     */
    public function __construct(
        public int $id,
        public int $tmdbId,
        public string $mediaType,
        public ?string $content,
        public int $rating,
        public ?string $sentiment,
        public bool $hasSpoilers,
        public bool $isApproved,
        public string $status,
        public int $reportsCount,
        public array $reasons,
        public ?int $authorId,
        public ?string $authorName,
        public ?string $authorUsername,
        public ?string $movieTitle,
        public ?string $moviePosterPath,
        public ?string $movieReleaseDate,
        public ?string $createdAt,
        public ?array $moderationLabels,
    ) {}

    /**
     * Needs the review's `user` and `reports` loaded. `$movie` is its catalogue entry; only movies are in the catalogue.
     */
    public static function fromModel(Review $review, ?LocalMovie $movie = null): self
    {
        $reports = $review->reports;

        return new self(
            id: $review->id,
            tmdbId: (int) $review->tmdb_id,
            mediaType: (string) $review->media_type,
            content: $review->content,
            rating: (int) $review->rating,
            sentiment: $review->sentiment,
            hasSpoilers: (bool) $review->has_spoilers,
            isApproved: (bool) $review->is_approved,
            status: match (true) {
                $review->removed_at !== null => 'removed',
                $reports->contains(fn (ReviewReport $report): bool => $report->resolved_at === null) => 'flagged',
                (bool) $review->is_approved => 'approved',
                default => 'pending',
            },
            reportsCount: $reports->count(),
            reasons: array_values(array_filter(
                ReviewReport::REASONS,
                fn (string $reason): bool => $reports->contains('reason', $reason),
            )),
            authorId: $review->user?->id,
            authorName: $review->user?->name,
            authorUsername: $review->user?->username,
            movieTitle: $movie?->title,
            moviePosterPath: $movie?->poster_path,
            movieReleaseDate: $movie?->release_date?->toDateString(),
            createdAt: $review->created_at?->toJSON(),
            moderationLabels: $review->moderation_labels,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'tmdb_id' => $this->tmdbId,
            'media_type' => $this->mediaType,
            'content' => $this->content,
            'rating' => $this->rating,
            'sentiment' => $this->sentiment,
            'has_spoilers' => $this->hasSpoilers,
            'is_approved' => $this->isApproved,
            'moderation_labels' => $this->moderationLabels,
            'status' => $this->status,
            'reports_count' => $this->reportsCount,
            'reasons' => $this->reasons,
            'author' => ['id' => $this->authorId, 'name' => $this->authorName, 'username' => $this->authorUsername],
            'movie' => [
                'tmdb_id' => $this->tmdbId,
                'title' => $this->movieTitle,
                'poster_path' => $this->moviePosterPath,
                'release_date' => $this->movieReleaseDate,
            ],
            'created_at' => $this->createdAt,
        ];
    }
}
