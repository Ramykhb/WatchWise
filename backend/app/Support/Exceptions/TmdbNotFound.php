<?php

namespace App\Support\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * TMDB answered 404: the thing asked for doesn't exist. Repositories turn it into
 * a specific error (e.g. MovieNotFound); on its own it renders a plain 404.
 */
class TmdbNotFound extends RuntimeException
{
    public function __construct(string $path)
    {
        parent::__construct("TMDB has nothing at {$path}.");
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Not found.'], 404);
    }
}
