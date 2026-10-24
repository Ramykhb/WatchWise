<?php

namespace App\Tmdb\Controllers;

use App\Http\Controllers\Controller;
use App\Tmdb\InterfaceServices\GenreServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GenreController extends Controller
{
    public function __construct(private readonly GenreServiceInterface $genres) {}

    /**
     * List TMDB's movie genres, or tv genres with ?type=tv (id and name), cached for a day.
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type') === 'tv' ? 'tv' : 'movie';

        return response()->json(['genres' => $this->genres->all($type)]);
    }
}
