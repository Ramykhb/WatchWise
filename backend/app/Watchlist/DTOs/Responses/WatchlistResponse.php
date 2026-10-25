<?php

namespace App\Watchlist\DTOs\Responses;

use Illuminate\Support\Collection;
use JsonSerializable;

/** Every watchlist row plus the user's ratings, for the History tab. */
final readonly class WatchlistResponse implements JsonSerializable
{
    /**
     * @param  list<WatchlistItemResponse>  $items
     * @param  Collection<int, int>  $ratings  the user's review rating by TMDB id
     */
    public function __construct(
        public array $items,
        public Collection $ratings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'items' => $this->items,
            'ratings' => $this->ratings,
        ];
    }
}
