<?php

namespace App\Person\Repository;

use App\Person\Exceptions\PersonNotFound;
use App\Person\InterfaceRepository\PersonRepositoryInterface;
use App\Support\Exceptions\TmdbNotFound;
use App\Support\TmdbHttpClient;

class PersonRepository implements PersonRepositoryInterface
{
    public function __construct(private readonly TmdbHttpClient $client) {}

    public function find(int $id): array
    {
        try {
            // One call: append_to_response bundles the person's movie credits with their profile.
            return $this->client->get("/person/{$id}", ['append_to_response' => 'movie_credits'])->json();
        } catch (TmdbNotFound) {
            throw new PersonNotFound($id);
        }
    }

    public function movieCredits(int $movieId): array
    {
        try {
            return $this->client->get("/movie/{$movieId}/credits")->json();
        } catch (TmdbNotFound) {
            // A film TMDB has since removed just adds no one, instead of failing the whole list.
            return ['cast' => [], 'crew' => []];
        }
    }
}
