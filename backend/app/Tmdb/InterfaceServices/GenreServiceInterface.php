<?php

namespace App\Tmdb\InterfaceServices;

use App\Tmdb\DTOs\Responses\GenreResponse;
use App\Tmdb\Exceptions\TmdbUnavailable;

interface GenreServiceInterface
{
    /**
     * The movie genres, cached for a day.
     *
     * @return list<GenreResponse>
     *
     * @throws TmdbUnavailable
     */
    public function all(): array;
}
