<?php

namespace App\Http\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Http\Resources\MemberResource;
use App\Http\Resources\MovieListResource;
use App\Http\Resources\ReviewResource;
use App\Models\User;
use App\Services\ActivityFeedService;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Public profiles, member search and following between users. */
class ProfileController extends Controller
{
    public function __construct(
        private readonly ActivityFeedService $activity,
        private readonly Notifier $notifier,
    ) {}

    /** GET /api/users?q= — find members by name or @username (invite pickers, search). */
    public function index(Request $request, CurrentUser $current): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->where('is_active', true)
            ->whereKeyNot($current->id())
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('username', 'like', '%'.ltrim($q, '@').'%')))
            ->orderBy('name')
            ->limit(10)
            ->get();

        return response()->json(['users' => MemberResource::collection($users)]);
    }

    /** GET /api/users/{username} — everything the Profile page shows, filtered by the owner's privacy settings. */
    public function show(User $user, CurrentUser $current): JsonResponse
    {
        $user->load('preferences');
        $isMe = $current->id() === $user->id;
        $viewer = $current->user();

        $summary = [
            'user' => [
                ...(new MemberResource($user))->resolve(),
                'bio' => $user->bio,
                'location' => $user->location,
                'profile_visibility' => $user->profile_visibility,
                'member_since' => $user->created_at?->toJSON(),
            ],
            'is_me' => $isMe,
            'is_following' => ! $isMe && $user->isFollowedBy($current->id()),
            'follows_you' => ! $isMe && $viewer !== null && $viewer->isFollowedBy($user->id),
            'counts' => [
                'followers' => $user->followers()->count(),
                'following' => $user->following()->count(),
                'reviews' => $user->reviews()->count(),
                'lists' => $user->movieLists()->when(! $isMe, fn ($q) => $q->where('is_public', true))->count(),
                'films' => $user->watchlists()->where('status', 'watched')->count(),
            ],
        ];

        if (! Gate::allows('view', $user)) {
            return response()->json([...$summary, 'restricted' => true]);
        }

        $shows = fn (string $key) => $isMe || $user->shares($key);

        $reviews = $shows('reviews')
            ? $user->reviews()->with('user')->latest()->limit(20)->get()
            : collect();
        $lists = $shows('lists')
            ? $user->movieLists()->with(['user', 'items'])->withCount('items')
                ->when(! $isMe, fn ($q) => $q->where('is_public', true))
                ->latest('updated_at')->get()
            : collect();
        $watched = $shows('history')
            ? $user->watchlists()->where('status', 'watched')->latest('watched_at')->limit(24)->pluck('tmdb_id')
            : collect();
        $activity = $this->activity->forUsers([$user->id], 20, $isMe)
            ->reject(fn ($item) => ! $shows('ratings') && $item['kind'] === 'rated')
            ->reject(fn ($item) => ! $shows('reviews') && $item['kind'] === 'reviewed')
            ->reject(fn ($item) => ! $shows('watchlist') && $item['kind'] === 'watchlist')
            ->values();

        return response()->json([
            ...$summary,
            'restricted' => false,
            'activity' => $activity,
            'reviews' => ReviewResource::collection($reviews),
            'lists' => MovieListResource::collection($lists),
            'watched' => $watched->values(),
            // Highest-rated films first; ties go to the most recent review.
            'top4' => $user->reviews()->orderByDesc('rating')->latest()->limit(4)->pluck('tmdb_id'),
            'genres' => $user->preferences?->favorite_genres ?? [],
            'followers' => MemberResource::collection($user->followers()->limit(8)->get()),
            'following' => $shows('following') ? MemberResource::collection($user->following()->limit(8)->get()) : [],
            'my_following' => $current->user()?->following()->pluck('users.id') ?? [],
        ]);
    }

    /** GET /api/users/{username}/followers */
    public function followers(User $user): JsonResponse
    {
        Gate::authorize('view', $user);

        return response()->json(['users' => MemberResource::collection($user->followers()->orderBy('name')->get())]);
    }

    /** GET /api/users/{username}/following */
    public function following(User $user, CurrentUser $current): JsonResponse
    {
        Gate::authorize('view', $user);
        abort_unless($current->id() === $user->id || $user->shares('following'), 403);

        return response()->json(['users' => MemberResource::collection($user->following()->orderBy('name')->get())]);
    }

    /** POST /api/users/{username}/follow */
    public function follow(User $user, CurrentUser $current): JsonResponse
    {
        Gate::authorize('follow', $user);
        $me = $current->user();

        $changes = $me->following()->syncWithoutDetaching([$user->id]);
        if ($changes['attached'] !== []) {
            $this->notifier->followed($me, $user);
        }

        return response()->json(['following' => true, 'followers' => $user->followers()->count()]);
    }

    /** DELETE /api/users/{username}/follow */
    public function unfollow(User $user, CurrentUser $current): JsonResponse
    {
        $current->user()->following()->detach($user->id);

        return response()->json(['following' => false, 'followers' => $user->followers()->count()]);
    }
}
