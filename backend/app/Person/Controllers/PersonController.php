<?php

namespace App\Person\Controllers;

use App\Person\FormRequests\PersonSearchRequest;
use App\Person\InterfaceServices\PersonServiceInterface;
use Illuminate\Http\JsonResponse;

class PersonController
{
    public function __construct(private readonly PersonServiceInterface $people) {}

    public function search(PersonSearchRequest $request): JsonResponse
    {
        return response()->json(['people' => $this->people->search($request->validated('q'))]);
    }

    /**
     * One TMDB person (actor, director, …) with their best-known films and full filmography.
     */
    public function show(int $id): JsonResponse
    {
        return response()->json($this->people->find($id));
    }

    /**
     * The three people who most often work with this person, from their best-known films' credits.
     */
    public function collaborators(int $id): JsonResponse
    {
        return response()->json($this->people->collaborators($id));
    }
}
