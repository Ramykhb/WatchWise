<?php

namespace App\Http\Controllers;

use App\Services\TmdbService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TmdbDiscoverController extends Controller
{
    /**
     * Discover's sort options and the TMDB sort each one uses.
     *
     * @var array<string, string>
     */
    public const SORTS = [
        'popularity' => 'popularity.desc',
        'rating' => 'vote_average.desc',
        'release_date' => 'primary_release_date.desc',
        'title' => 'title.asc',
    ];

    /**
     * Browse TMDB movies with Discover's filters.
     */
    public function __invoke(Request $request, TmdbService $tmdb): JsonResponse
    {
        $validated = $request->validate([
            'genres' => ['sometimes', 'string', 'regex:/^\d+(,\d+)*$/'],
            'year_from' => ['sometimes', 'integer', 'between:1874,2100'],
            'year_to' => ['sometimes', 'integer', 'between:1874,2100', 'gte:year_from'],
            'min_rating' => ['sometimes', 'numeric', 'between:0,5'],
            'runtime_min' => ['sometimes', 'integer', 'min:0'],
            'runtime_max' => ['sometimes', 'integer', 'min:1', 'gte:runtime_min'],
            'language' => ['sometimes', 'string', 'regex:/^[a-z]{2}$/'],
            'sort' => ['sometimes', 'string', Rule::in(array_keys(self::SORTS))],
            'page' => ['sometimes', 'integer', 'between:1,500'],
        ]);

        $sort = $validated['sort'] ?? 'popularity';

        try {
            $response = $tmdb->get('/discover/movie', array_filter([
                'with_genres' => $validated['genres'] ?? null,
                'primary_release_date.gte' => isset($validated['year_from']) ? "{$validated['year_from']}-01-01" : null,
                'primary_release_date.lte' => isset($validated['year_to']) ? "{$validated['year_to']}-12-31" : null,
                'vote_average.gte' => isset($validated['min_rating']) ? (float) $validated['min_rating'] * 2 : null,
                'with_runtime.gte' => isset($validated['runtime_min']) ? (int) $validated['runtime_min'] : null,
                'with_runtime.lte' => isset($validated['runtime_max']) ? (int) $validated['runtime_max'] : null,
                'with_original_language' => $validated['language'] ?? null,
                // Without a vote floor, "Rating" is topped by films with a single 10/10 vote.
                'vote_count.gte' => $sort === 'rating' ? 100 : null,
                'sort_by' => self::SORTS[$sort],
                'page' => (int) ($validated['page'] ?? 1),
                'include_adult' => 'false',
            ], fn ($value): bool => $value !== null));
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return $tmdb->unavailableResponse();
        }

        return response()->json(['sort' => $sort, ...$tmdb->formatPage($response)]);
    }
}
