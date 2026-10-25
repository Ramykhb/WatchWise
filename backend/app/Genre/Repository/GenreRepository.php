<?php

namespace App\Genre\Repository;

use App\Genre\InterfaceRepository\GenreRepositoryInterface;
use App\Support\TmdbHttpClient;

class GenreRepository implements GenreRepositoryInterface
{
    public function __construct(private readonly TmdbHttpClient $client) {}

    public function all(string $type = 'movie'): array
    {
        $genres = $this->client->get("/genre/{$type}/list")->json('genres', []);

        return array_values(array_map(
            fn (array $genre): array => ['id' => (int) $genre['id'], 'name' => (string) $genre['name']],
            $genres,
        ));
    }
}
