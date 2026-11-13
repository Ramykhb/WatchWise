<?php

namespace App\Admin\DTOs\Responses;

use JsonSerializable;

/** What a bulk decision did to each review it named, in the order they were named. */
final readonly class BulkModerateReviewsResponse implements JsonSerializable
{
    /**
     * @param  list<array{id: int, status: 'approved'|'rejected'|'skipped', reason: 'not_found'|'no_change'|'failed'|null}>  $results
     */
    public function __construct(
        public array $results,
        public int $applied,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return ['results' => $this->results, 'applied' => $this->applied];
    }
}
