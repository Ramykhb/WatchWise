<?php

namespace App\Tmdb\InterfaceServices;

use App\Tmdb\DTOs\Requests\MovieDiscoverQuery;
use App\Tmdb\DTOs\Requests\MovieListQuery;
use App\Tmdb\DTOs\Requests\MovieSearchQuery;
use App\Tmdb\DTOs\Responses\MoviePageResponse;
use App\Tmdb\Exceptions\TmdbUnavailable;

interface MovieServiceInterface
{
    /** @throws TmdbUnavailable */
    public function list(MovieListQuery $query): MoviePageResponse;

    /** @throws TmdbUnavailable */
    public function search(MovieSearchQuery $query): MoviePageResponse;

    /** @throws TmdbUnavailable */
    public function discover(MovieDiscoverQuery $query): MoviePageResponse;
}
