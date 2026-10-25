<?php

namespace App\Genre\InterfaceServices;

use App\Genre\DTOs\Responses\GenreResponse;
use App\Support\Exceptions\TmdbUnavailable;

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
