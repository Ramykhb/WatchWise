<?php

namespace App\MovieList\DTOs\Responses;

use App\Models\MovieList;
use App\MovieList\Resources\MovieListResource;
use Illuminate\Contracts\Pagination\Paginator;
use JsonSerializable;

/** One page of `GET /api/lists`: the lists, plus where the page sits so clients can ask for the next one. */
final readonly class MovieListPageResponse implements JsonSerializable
{
    /** @param Paginator<int, MovieList> $page */
    public function __construct(private Paginator $page) {}

    /**
     * @return array{lists: mixed, meta: array{current_page: int, per_page: int, has_more: bool}}
     */
    public function jsonSerialize(): array
    {
        return [
            'lists' => MovieListResource::collection($this->page->items()),
            'meta' => [
                'current_page' => $this->page->currentPage(),
                'per_page' => $this->page->perPage(),
                'has_more' => $this->page->hasMorePages(),
            ],
        ];
    }
}
