<?php

namespace App\Genre\Services;

use App\Genre\DTOs\Responses\GenreResponse;
use App\Genre\InterfaceRepository\GenreRepositoryInterface;
use App\Genre\InterfaceServices\GenreServiceInterface;
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
