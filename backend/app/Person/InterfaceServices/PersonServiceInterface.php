<?php

namespace App\Person\InterfaceServices;

use App\Person\DTOs\Responses\CollaboratorsResponse;
use App\Person\DTOs\Responses\PersonDetailsResponse;
use App\Person\Exceptions\PersonNotFound;
use App\Support\Exceptions\TmdbUnavailable;

interface PersonServiceInterface
{
    /**
     * One person with their best-known films and their filmography, newest first.
     *
     * @throws PersonNotFound
     * @throws TmdbUnavailable
     */
    public function find(int $id): PersonDetailsResponse;

    /**
     * The (up to three) people who appear most often in this person's best-known films, each in at least two.
     * Empty when TMDB can't be reached, so the page still works without them.
     *
     * @throws PersonNotFound
     */
    public function collaborators(int $id): CollaboratorsResponse;
}
