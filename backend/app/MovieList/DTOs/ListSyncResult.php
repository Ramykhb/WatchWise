<?php

namespace App\MovieList\DTOs;

use App\MovieList\DTOs\Requests\ListFilmData;

/** What saving a list's films changed. */
final readonly class ListSyncResult
{
    /**
     * @param  list<ListFilmData>  $added  titles that were not in the list before
     * @param  bool  $changed  whether titles were added or removed, or their order changed
     */
    public function __construct(
        public array $added,
        public bool $changed,
    ) {}
}
