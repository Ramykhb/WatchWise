<?php

namespace App\Http\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\Watchlist;
use App\Services\ActivityFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** The signed-in user's watchlist and watch history (watchlist rows with status "watched"). */
class WatchlistController extends Controller
{
    public function __construct(private readonly ActivityFeedService $activity) {}

    /** GET /api/watchlist — every row plus the user's ratings, for the History tab. */
    public function index(CurrentUser $current): JsonResponse
    {
        $user = $current->user();
        $items = $user->watchlists()->latest()->get();

        return response()->json([
            'items' => $items->map(fn (Watchlist $item) => $this->present($item))->values(),
            'ratings' => $user->reviews()->pluck('rating', 'tmdb_id'),
        ]);
    }

    /** POST /api/watchlist */
    public function store(Request $request, CurrentUser $current): JsonResponse
    {
        Gate::authorize('create', Watchlist::class);
        $data = $request->validate([
            'tmdb_id' => ['required', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::in(['to_watch', 'watching', 'watched'])],
        ]);
        $user = $current->user();

        $item = $user->watchlists()->firstOrNew(['tmdb_id' => $data['tmdb_id']]);
        $isNew = ! $item->exists;
        $item->status = $data['status'] ?? $item->status ?? 'to_watch';
        $item->watched_at = $item->status === 'watched' ? ($item->watched_at ?? now()) : null;
        $item->save();

        if ($isNew) {
            $this->activity->record($user, 'watchlist_add', $item, $item->tmdb_id, $user->shares('watchlist'));
        }

        return response()->json(['item' => $this->present($item)], $isNew ? 201 : 200);
    }

    /** PATCH /api/watchlist/{watchlist} — e.g. mark as watched. */
    public function update(Request $request, Watchlist $watchlist): JsonResponse
    {
        Gate::authorize('update', $watchlist);
        $data = $request->validate(['status' => ['required', Rule::in(['to_watch', 'watching', 'watched'])]]);

        $watchlist->status = $data['status'];
        $watchlist->watched_at = $data['status'] === 'watched' ? now() : null;
        $watchlist->save();

        return response()->json(['item' => $this->present($watchlist)]);
    }

    /** DELETE /api/watchlist/{watchlist} */
    public function destroy(Watchlist $watchlist): JsonResponse
    {
        Gate::authorize('delete', $watchlist);
        $watchlist->delete();

        return response()->json(['message' => 'Removed from your watchlist.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Watchlist $item): array
    {
        return [
            'id' => $item->id,
            'tmdb_id' => $item->tmdb_id,
            'status' => $item->status,
            'watched_at' => $item->watched_at?->toJSON(),
            'created_at' => $item->created_at?->toJSON(),
        ];
    }
}
