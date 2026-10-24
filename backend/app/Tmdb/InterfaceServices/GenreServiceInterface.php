<?php

namespace App\Tmdb\InterfaceServices;

use App\Tmdb\DTOs\Responses\GenreResponse;
use App\Tmdb\Exceptions\TmdbUnavailable;

interface GenreServiceInterface
{
    /**
     * The movie or tv genres, cached for a day.
     *
     * @param  'movie'|'tv'  $type
     * @return list<GenreResponse>
     *
     * @throws TmdbUnavailable
     */
    public function all(string $type = 'movie'): array;
}
