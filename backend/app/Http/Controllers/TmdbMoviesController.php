<?php

namespace App\Http\Controllers;

use App\Services\TmdbService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TmdbMoviesController extends Controller
{
    /**
     * The TMDB movie lists the frontend can ask for.
     *
     * @var list<string>
     */
    public const LISTS = ['popular', 'top_rated', 'now_playing', 'upcoming'];

    /**
     * Fetch one page of a TMDB movie list.
     */
    public function __invoke(Request $request, TmdbService $tmdb): JsonResponse
    {
        $validated = $request->validate([
            'list' => ['sometimes', 'string', Rule::in(self::LISTS)],
            'page' => ['sometimes', 'integer', 'between:1,500'],
        ]);

        $list = $validated['list'] ?? 'popular';

        try {
            $response = $tmdb->get("/movie/{$list}", ['page' => (int) ($validated['page'] ?? 1)]);
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return $tmdb->unavailableResponse();
        }

        return response()->json(['list' => $list, ...$tmdb->formatPage($response)]);
    }
}
