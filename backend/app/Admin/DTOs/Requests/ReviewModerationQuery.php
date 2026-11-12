<?php

namespace App\Admin\DTOs\Requests;

/** One page of the reviews an admin can moderate. */
final readonly class ReviewModerationQuery
{
    public const STATUSES = ['pending', 'approved', 'flagged', 'removed'];

    public const SENTIMENTS = ['positive', 'neutral', 'negative'];

    public const REASONS = ['Spam', 'Links', 'Spoilers', 'Offensive language', 'offensive language', 'spoiler', 'spam', 'awaiting classification'];

    public const MAX_PER_PAGE = 50;

    /**
     * @param  'pending'|'approved'|'flagged'|'removed'  $status
     */
    public function __construct(
        public string $status = 'pending',
        public int $page = 1,
        public int $perPage = 20,
        public string $search = '',
        public ?string $sentiment = null,
        public ?string $reason = null,
        public ?int $userId = null,
        public ?int $tmdbId = null,
        public ?string $mediaType = null,
    ) {}

    /**
     * @param  array{status?: string|null, page?: int|string, per_page?: int|string, q?: string|null, sentiment?: string|null, reason?: string|null, user_id?: int|string|null, tmdb_id?: int|string|null, media_type?: string|null}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            $validated['status'] ?? 'pending',
            max(1, (int) ($validated['page'] ?? 1)),
            min(self::MAX_PER_PAGE, max(1, (int) ($validated['per_page'] ?? 20))),
            trim((string) ($validated['q'] ?? '')),
            $validated['sentiment'] ?? null,
            $validated['reason'] ?? null,
            isset($validated['user_id']) ? (int) $validated['user_id'] : null,
            isset($validated['tmdb_id']) ? (int) $validated['tmdb_id'] : null,
            $validated['media_type'] ?? null,
        );
    }
}
