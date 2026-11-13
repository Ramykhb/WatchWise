<?php

namespace App\Genre\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** TMDB's genre list for that type has no genre with that id. */
class GenreNotFound extends RuntimeException
{
    public function __construct(string $type, int $tmdbId)
    {
        parent::__construct("TMDB has no {$type} genre {$tmdbId}.");
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Genre not found.'], 404);
    }
}
