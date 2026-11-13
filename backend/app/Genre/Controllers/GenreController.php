<?php

namespace App\Genre\Controllers;

use App\Genre\FormRequests\GenreIndexRequest;
use App\Genre\InterfaceServices\GenreServiceInterface;
use Illuminate\Http\JsonResponse;

class GenreController
{
    public function __construct(private readonly GenreServiceInterface $genres) {}

    /**
     * List TMDB's movie genres, or tv genres with ?type=tv (id and name), with staff's overrides applied. Genres
     * hidden from members are left out unless ?include_hidden=1, which name lookups use.
     */
    public function index(GenreIndexRequest $request): JsonResponse
    {
        $query = $request->toDto();

        return response()->json(['genres' => $query->includeHidden
            ? $this->genres->allIncludingHidden($query->type)
            : $this->genres->all($query->type)]);
    }
}
