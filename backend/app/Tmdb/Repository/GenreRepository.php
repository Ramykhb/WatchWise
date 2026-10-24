<?php

namespace App\Tmdb\Repository;

use App\Tmdb\InterfaceRepository\GenreRepositoryInterface;
use App\Tmdb\Support\TmdbHttpClient;

class GenreRepository implements GenreRepositoryInterface
{
    public function __construct(private readonly TmdbHttpClient $client) {}

    public function all(): array
    {
        $genres = $this->client->get('/genre/movie/list')->json('genres', []);

        return array_values(array_map(
            fn (array $genre): array => ['id' => (int) $genre['id'], 'name' => (string) $genre['name']],
            $genres,
        ));
    }
}
