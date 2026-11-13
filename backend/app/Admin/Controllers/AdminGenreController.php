<?php

namespace App\Admin\Controllers;

use App\Admin\FormRequests\AdminGenreIndexRequest;
use App\Admin\FormRequests\UpdateGenreOverrideRequest;
use App\Admin\InterfaceServices\AdminGenreServiceInterface;
use App\Auth\CurrentUser\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** The genres page: TMDB's genres with the local display name, hidden flag and position staff layer on top. */
class AdminGenreController
{
    public function __construct(private readonly AdminGenreServiceInterface $genres) {}

    /** GET /api/admin/genres */
    public function index(AdminGenreIndexRequest $request): JsonResponse
    {
        return response()->json(['genres' => $this->genres->list($request->toDto())]);
    }

    /** PATCH /api/admin/genres/{type}/{tmdbId} — rename, hide or place a genre. */
    public function update(UpdateGenreOverrideRequest $request, string $type, int $tmdbId, CurrentUser $current): JsonResponse
    {
        return response()->json(['genre' => $this->genres->update($current->user(), $type, $tmdbId, $request->toDto())]);
    }

    /** DELETE /api/admin/genres/{type}/{tmdbId} — back to what TMDB has. */
    public function destroy(string $type, int $tmdbId, CurrentUser $current): JsonResponse
    {
        Gate::authorize('admin.genres');
        $this->genres->reset($current->user(), $type, $tmdbId);

        return response()->json(['message' => 'Genre reset.']);
    }
}
