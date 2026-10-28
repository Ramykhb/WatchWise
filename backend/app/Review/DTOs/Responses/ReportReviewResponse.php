<?php

namespace App\Review\DTOs\Responses;

use JsonSerializable;

/** The outcome of reporting a review; `created` is false when the member had already reported it. */
final readonly class ReportReviewResponse implements JsonSerializable
{
    public function __construct(
        public bool $created,
        public string $reason,
    ) {}

    /**
     * @return array{message: string, reason: string}
     */
    public function jsonSerialize(): array
    {
        return ['message' => 'Review reported.', 'reason' => $this->reason];
    }
}
