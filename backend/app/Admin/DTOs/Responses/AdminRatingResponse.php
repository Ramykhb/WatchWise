<?php

namespace App\Admin\DTOs\Responses;

use App\Models\LocalMovie;
use App\Models\Review;
use JsonSerializable;

/**
 * A star rating in the staff list, with its author and the film it is about. `moderated` marks the stars left
 * behind when a moderator removed the review's text; those rows are not deleted through the rating tools.
 */
final readonly class AdminRatingResponse implements JsonSerializable
{
    public function __construct(
        public int $id,
        public int $tmdbId,
        public string $mediaType,
        public int $rating,
        public bool $moderated,
        public ?int $authorId,
        public ?string $authorName,
        public ?string $authorUsername,
        public ?string $filmTitle,
        public ?string $filmPosterPath,
        public ?string $createdAt,
    ) {}

    /**
     * Needs the rating's `user` loaded. `$film` is its catalogue entry; only movies are in the catalogue.
     */
    public static function fromModel(Review $rating, ?LocalMovie $film = null): self
    {
        return new self(
            id: $rating->id,
            tmdbId: (int) $rating->tmdb_id,
            mediaType: (string) $rating->media_type,
            rating: (int) $rating->rating,
            moderated: $rating->removed_at !== null,
            authorId: $rating->user?->id,
            authorName: $rating->user?->name,
            authorUsername: $rating->user?->username,
            filmTitle: $film?->title,
            filmPosterPath: $film?->poster_path,
            createdAt: $rating->created_at?->toJSON(),
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
            'rating' => $this->rating,
            'moderated' => $this->moderated,
            'author' => ['id' => $this->authorId, 'name' => $this->authorName, 'username' => $this->authorUsername],
            'film' => $this->filmTitle !== null ? ['tmdb_id' => $this->tmdbId, 'title' => $this->filmTitle, 'poster_path' => $this->filmPosterPath] : null,
            'created_at' => $this->createdAt,
        ];
    }
}
