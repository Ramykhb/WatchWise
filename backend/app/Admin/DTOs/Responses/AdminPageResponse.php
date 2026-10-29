<?php

namespace App\Admin\DTOs\Responses;

use JsonSerializable;

/** One page of an admin table: the rows plus where the page sits in the whole list, and optional whole-table totals. */
final readonly class AdminPageResponse implements JsonSerializable
{
    /**
     * @param  list<JsonSerializable>  $items
     * @param  array<string, mixed>|null  $summary  totals across the whole table, not just this page
     */
    public function __construct(
        public array $items,
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total,
        public ?array $summary = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $page = [
            'data' => $this->items,
            'meta' => [
                'current_page' => $this->currentPage,
                'last_page' => $this->lastPage,
                'per_page' => $this->perPage,
                'total' => $this->total,
            ],
        ];

        if ($this->summary !== null) {
            $page['summary'] = $this->summary;
        }

        return $page;
    }

    /** The same page with `$summary` attached. */
    public function withSummary(array $summary): self
    {
        return new self($this->items, $this->currentPage, $this->lastPage, $this->perPage, $this->total, $summary);
    }
}
