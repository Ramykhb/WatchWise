<?php

namespace App\Movie\InterfaceRepository;

use App\Movie\DTOs\Requests\MovieDiscoverQuery;
use App\Movie\DTOs\Requests\MovieListQuery;
use App\Movie\DTOs\Requests\MovieSearchQuery;
use App\Movie\DTOs\Requests\MovieTrendingQuery;
use App\Movie\Exceptions\MovieNotFound;
use App\Support\Exceptions\TmdbUnavailable;

/**
 * Reads movie pages from TMDB. Returns TMDB's decoded page payload
 * (page, total_pages, total_results, results); the service turns it into DTOs.
 */
interface MovieRepositoryInterface
{
    /** @return array<string, mixed> */
    public function trending(MovieTrendingQuery $query): array;

    /**
     * @return array<string, mixed>
     *
     * @throws TmdbUnavailable
     */
    public function list(MovieListQuery $query): array;

    /**
     * @return array<string, mixed>
     *
     * @throws TmdbUnavailable
     */
    public function search(MovieSearchQuery $query): array;

    /**
     * @return array<string, mixed>
     *
     * @throws TmdbUnavailable
     */
    public function discover(MovieDiscoverQuery $query): array;

    /**
     * @return array<string, mixed>
     *
     * @throws TmdbUnavailable
     */
    public function searchTv(MovieSearchQuery $query): array;

    /**
     * @return array<string, mixed>
     *
     * @throws TmdbUnavailable
     */
    public function discoverTv(MovieDiscoverQuery $query): array;

    /**
     * One movie (or tv show) with its credits, videos, recommendations, similar titles and age ratings.
     *
     * @param  'movie'|'tv'  $type
     * @return array<string, mixed>
     *
     * @throws MovieNotFound
     * @throws TmdbUnavailable
     */
    public function find(int $id, string $type = 'movie'): array;

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
