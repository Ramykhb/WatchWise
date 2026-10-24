<?php

namespace App\Tmdb\InterfaceRepository;

use App\Tmdb\Exceptions\TmdbUnavailable;

interface GenreRepositoryInterface
{
    /**
     * TMDB's movie genres as returned by the API.
     *
     * @return list<array{id: int, name: string}>
     *
     * @throws TmdbUnavailable
     */
    public function all(): array;
}
