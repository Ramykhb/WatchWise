<?php

namespace App\Tmdb\Services;

use App\Tmdb\DTOs\Responses\GenreResponse;
use App\Tmdb\InterfaceRepository\GenreRepositoryInterface;
use App\Tmdb\InterfaceServices\GenreServiceInterface;
use Illuminate\Support\Facades\Cache;

class GenreService implements GenreServiceInterface
{
    public function __construct(private readonly GenreRepositoryInterface $genres) {}

    /**
     * @param  'movie'|'tv'  $type
     * @return list<GenreResponse>
     */
    public function all(string $type = 'movie'): array
    {
        $genres = Cache::remember("tmdb.{$type}_genres", now()->addDay(), fn () => $this->genres->all($type));

        return array_map(
            fn (array $genre) => new GenreResponse($genre['id'], $genre['name']),
            array_values($genres),
        );
    }
}
