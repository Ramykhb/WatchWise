<?php

namespace App\Admin\DTOs\Requests;

/** What staff change on one review: its moderation decision, its spoiler flag, or both. */
final readonly class UpdateReviewData
{
    public function __construct(
        public ?ModerateReviewData $decision = null,
        public ?bool $hasSpoilers = null,
    ) {}

    /**
     * @param  array{status?: string|null, has_spoilers?: bool|null}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            isset($validated['status']) ? ModerateReviewData::fromValidated(['status' => $validated['status']]) : null,
            $validated['has_spoilers'] ?? null,
        );
    }
}
