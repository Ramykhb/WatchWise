<?php

namespace App\Genre\DTOs\Requests;

/** Which genre list `GET /api/genres` returns, and whether genres staff hid from members are kept in it. */
final readonly class GenreListQuery
{
    /**
     * @param  'movie'|'tv'  $type
     */
    public function __construct(
        public string $type = 'movie',
        public bool $includeHidden = false,
    ) {}
}
