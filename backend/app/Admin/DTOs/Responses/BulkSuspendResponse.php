<?php

namespace App\Admin\DTOs\Responses;

use JsonSerializable;

/** What a bulk suspension did to each member asked for, in the order they were sent. */
final readonly class BulkSuspendResponse implements JsonSerializable
{
    /**
     * @param  list<array{id: int, status: 'suspended'|'skipped', reason: 'already_suspended'|'not_allowed'|'self'|'not_found'|'failed'|null}>  $results
     */
    public function __construct(public array $results) {}

    /**
     * @return array{results: list<array<string, mixed>>, suspended: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'results' => $this->results,
            'suspended' => count(array_filter($this->results, fn (array $result): bool => $result['status'] === 'suspended')),
        ];
    }
}
