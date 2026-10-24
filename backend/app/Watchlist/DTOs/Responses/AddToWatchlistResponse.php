<?php

namespace App\Watchlist\DTOs\Responses;

/** The film that was added or updated, and whether it was new (the endpoint answers 201 for new, 200 otherwise). */
final readonly class AddToWatchlistResponse
{
    public function __construct(
        public WatchlistItemResponse $item,
        public bool $created,
    ) {}
}
