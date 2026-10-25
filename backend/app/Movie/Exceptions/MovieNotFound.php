<?php

namespace App\Movie\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * TMDB has no movie with this id. Rendered as a 404 so the frontend can show "Movie not found".
 */
class MovieNotFound extends RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct("TMDB has no movie {$id}.");
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Movie not found.'], 404);
    }
}
