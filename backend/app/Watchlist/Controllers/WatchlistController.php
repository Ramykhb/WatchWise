<?php

namespace App\Watchlist\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Http\Controllers\Controller;
use App\Models\Watchlist;
use App\Watchlist\FormRequests\StoreWatchlistRequest;
use App\Watchlist\FormRequests\UpdateWatchlistRequest;
use App\Watchlist\InterfaceServices\WatchlistServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** The signed-in user's watchlist and watch history (watchlist rows with status "watched"). */
class WatchlistController extends Controller
{
    public function __construct(private readonly WatchlistServiceInterface $watchlist) {}

    /** GET /api/watchlist — every row plus the user's ratings, for the History tab. */
    public function index(CurrentUser $current): JsonResponse
    {
        return response()->json($this->watchlist->overview($current->user()));
    }

    /** POST /api/watchlist */
    public function store(StoreWatchlistRequest $request, CurrentUser $current): JsonResponse
    {
        $added = $this->watchlist->add($current->user(), $request->toDto());

        return response()->json(['item' => $added->item], $added->created ? 201 : 200);
    }

    /** PATCH /api/watchlist/{watchlist} — e.g. mark as watched. */
    public function update(UpdateWatchlistRequest $request, Watchlist $watchlist): JsonResponse
    {
        return response()->json(['item' => $this->watchlist->updateStatus($watchlist, $request->toDto())]);
    }

    /** DELETE /api/watchlist/{watchlist} */
    public function destroy(Watchlist $watchlist): JsonResponse
    {
        Gate::authorize('delete', $watchlist);
        $this->watchlist->remove($watchlist);

        return response()->json(['message' => 'Removed from your watchlist.']);
    }
}
