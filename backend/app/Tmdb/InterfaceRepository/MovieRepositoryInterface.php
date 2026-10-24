<?php

namespace App\Tmdb\InterfaceRepository;

use App\Tmdb\DTOs\Requests\MovieDiscoverQuery;
use App\Tmdb\DTOs\Requests\MovieListQuery;
use App\Tmdb\DTOs\Requests\MovieSearchQuery;
use App\Tmdb\Exceptions\MovieNotFound;
use App\Tmdb\Exceptions\TmdbUnavailable;

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
     * One movie with its credits, videos, recommendations, similar movies and release dates.
     *
     * @return array<string, mixed>
     *
     * @throws MovieNotFound
     * @throws TmdbUnavailable
     */
    public function find(int $id): array;
}
