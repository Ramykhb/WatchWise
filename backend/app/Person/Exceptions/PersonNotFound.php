<?php

namespace App\Person\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * TMDB has no person with this id. Rendered as a 404 so the frontend can show "Person not found".
 */
class PersonNotFound extends RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct("TMDB has no person {$id}.");
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Person not found.'], 404);
    }
}
