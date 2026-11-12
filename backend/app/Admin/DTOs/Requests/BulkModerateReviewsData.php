<?php

namespace App\Admin\DTOs\Requests;

/** One decision applied to several reviews at once. */
final readonly class BulkModerateReviewsData
{
    public const MAX_REVIEWS = 50;

    /**
     * @param  list<int>  $ids  distinct, in the order the caller gave them
     */
    public function __construct(
        public array $ids,
        public ModerateReviewData $decision,
    ) {}

    /**
     * @param  array{ids: list<int|string>, status: string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            array_map(intval(...), array_values($validated['ids'])),
            ModerateReviewData::fromValidated($validated),
        );
    }
}
