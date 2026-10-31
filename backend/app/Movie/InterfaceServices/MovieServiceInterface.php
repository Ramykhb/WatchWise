<?php

namespace App\Movie\InterfaceServices;

use App\Movie\DTOs\Requests\MovieDiscoverQuery;
use App\Movie\DTOs\Requests\MovieListQuery;
use App\Movie\DTOs\Requests\MovieSearchQuery;
use App\Movie\DTOs\Requests\MovieTrendingQuery;
use App\Movie\DTOs\Responses\MovieDetailsResponse;
use App\Movie\DTOs\Responses\MoviePageResponse;
use App\Movie\Exceptions\MovieNotFound;
use App\Support\Exceptions\TmdbUnavailable;

interface MovieServiceInterface
{
    /** Weekly trending titles in TMDB rank order. @throws TmdbUnavailable */
    public function trending(MovieTrendingQuery $query): MoviePageResponse;

    /** @throws TmdbUnavailable */
    public function list(MovieListQuery $query): MoviePageResponse;

    /** @throws TmdbUnavailable */
    public function search(MovieSearchQuery $query): MoviePageResponse;

    /** @throws TmdbUnavailable */
    public function discover(MovieDiscoverQuery $query): MoviePageResponse;

    /**
     * @param  'movie'|'tv'  $type
     *
     * @throws MovieNotFound
     * @throws TmdbUnavailable
     */
    public function find(int $id, string $type = 'movie'): MovieDetailsResponse;
}
