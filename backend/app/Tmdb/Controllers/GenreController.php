<?php

namespace App\Tmdb\Controllers;

use App\Http\Controllers\Controller;
use App\Tmdb\InterfaceServices\GenreServiceInterface;
use Illuminate\Http\JsonResponse;

class GenreController extends Controller
{
    public function __construct(private readonly GenreServiceInterface $genres) {}

    /**
     * List TMDB's movie genres (id and name), cached for a day.
     */
    public function index(): JsonResponse
    {
        return response()->json(['genres' => $this->genres->all()]);
    }
}
