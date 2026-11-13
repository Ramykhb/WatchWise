<?php

namespace App\Review\DTOs\Requests;

/** One page of the moderation queue, with the filters staff can narrow it by. */
final readonly class ModerationQueueQuery
{
    /**
     * @param  'pending'|'approved'|'flagged'|'removed'  $status
     * @param  string|null  $mediaType  `movie` or `tv`; together with `$tmdbId` it names one title
     */
    public function __construct(
        public string $status,
        public int $page,
        public int $perPage,
        public string $search = '',
        public ?string $sentiment = null,
        public ?string $reason = null,
        public ?int $userId = null,
        public ?int $tmdbId = null,
        public ?string $mediaType = null,
    ) {}
}
