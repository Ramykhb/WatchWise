<?php

namespace App\Genre\InterfaceRepository;

use App\Support\Exceptions\TmdbUnavailable;

interface GenreRepositoryInterface
{
    /**
     * TMDB's genres for movies or tv shows as returned by the API.
     *
     * @param  'movie'|'tv'  $type
     * @return list<array{id: int, name: string}>
     *
     * @throws TmdbUnavailable
     */
    public function all(string $type = 'movie'): array;
}
