<?php

namespace App\Movie\InterfaceRepository;

use App\Movie\DTOs\Requests\MovieDiscoverQuery;
use App\Movie\DTOs\Requests\MovieListQuery;
use App\Movie\DTOs\Requests\MovieSearchQuery;
use App\Movie\Exceptions\MovieNotFound;
use App\Support\Exceptions\TmdbUnavailable;

/**
 * Reads movie pages from TMDB. Returns TMDB's decoded page payload
 * (page, total_pages, total_results, results); the service turns it into DTOs.
 */
interface MovieRepositoryInterface
{
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
}
