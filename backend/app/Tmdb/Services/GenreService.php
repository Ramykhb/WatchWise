<?php

namespace App\Tmdb\Services;

use App\Tmdb\DTOs\Responses\GenreResponse;
use App\Tmdb\InterfaceRepository\GenreRepositoryInterface;
use App\Tmdb\InterfaceServices\GenreServiceInterface;
use Illuminate\Support\Facades\Cache;

class GenreService implements GenreServiceInterface
{
    private const CACHE_KEY = 'tmdb.movie_genres';

    public function __construct(private readonly GenreRepositoryInterface $genres) {}

    /**
     * @return list<GenreResponse>
     */
    public function all(): array
    {
        $genres = Cache::remember(self::CACHE_KEY, now()->addDay(), fn () => $this->genres->all());

        return array_map(
            fn (array $genre) => new GenreResponse($genre['id'], $genre['name']),
            array_values($genres),
        );
    }
}
