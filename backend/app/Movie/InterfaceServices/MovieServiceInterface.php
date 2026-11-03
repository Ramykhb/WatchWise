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

    /** One page of a TMDB list, cached for an hour. @throws TmdbUnavailable */
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

    /**
     * Full cast and crew for multiple movies/TV shows, keyed by MediaType::key().
     * Removed titles are omitted.
     *
     * @param  list<array{id: int, media_type: string}>  $titles
     * @return array<string, array{cast: array, crew: array}>
     *
     * @throws TmdbUnavailable
     */
    public function credits(array $titles): array;

    /**
     * Genre objects and complete credits for watched movies/TV shows, keyed by MediaType::key().
     * Removed titles are omitted.
     *
     * @param  list<array{id: int, media_type: string}>  $titles
     * @return array<string, array{genres: array, credits: array}>
     */
    public function metadata(array $titles): array;
}
