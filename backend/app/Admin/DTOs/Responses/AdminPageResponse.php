<?php

namespace App\Admin\DTOs\Responses;

use JsonSerializable;

/** One page of an admin table: the rows plus where the page sits in the whole list. */
final readonly class AdminPageResponse implements JsonSerializable
{
    /**
     * @param  list<JsonSerializable>  $items
     */
    public function __construct(
        public array $items,
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total,
    ) {}

    /**
     * @return array{data: list<JsonSerializable>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}
     */
    public function jsonSerialize(): array
    {
        return [
            'data' => $this->items,
            'meta' => [
                'current_page' => $this->currentPage,
                'last_page' => $this->lastPage,
                'per_page' => $this->perPage,
                'total' => $this->total,
            ],
        ];
    }
}
