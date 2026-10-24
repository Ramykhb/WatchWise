<?php

namespace App\Http\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Http\Resources\MemberResource;
use App\Http\Resources\MovieListResource;
use App\Http\Resources\ReviewResource;
use App\Models\User;
use App\Services\ActivityFeedService;
use App\Services\Notifier;
use App\Services\ProfilePrivacy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Public profiles, member search and following between users. */
class ProfileController extends Controller
{
    public function __construct(
        private readonly ActivityFeedService $activity,
        private readonly Notifier $notifier,
        private readonly ProfilePrivacy $privacy,
    ) {}

    /**
     * GET /api/users?q=&limit= — find members by name or @username (header search, Discover,
     * invite pickers). Without `q` it suggests the most-followed members. Each result says
     * whether the signed-in user follows them, so Follow buttons start in the right state.
     */
    public function index(Request $request, CurrentUser $current): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $limit = min(max((int) $request->query('limit', 10), 1), 30);
        $like = $this->containsPattern(ltrim($q, '@'));

        $query = User::query()
            ->where('is_active', true)
            ->whereKeyNot($current->id())
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->whereRaw("name LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("username LIKE ? ESCAPE '!'", [$like])));

        $total = (clone $query)->count();
        $users = $query->withCount('followers')
            ->when($q !== '', fn ($query) => $query->orderBy('name'), fn ($query) => $query->orderByDesc('followers_count')->orderBy('name'))
            ->limit($limit)
            ->get();

        $me = $current->user();
        $following = $me->following()->whereIn('users.id', $users->pluck('id'))->pluck('users.id')->all();
        $followers = $me->followers()->whereIn('users.id', $users->pluck('id'))->pluck('users.id')->all();

        return response()->json([
            'total' => $total,
            'users' => $users->map(fn (User $user) => [
                ...(new MemberResource($user))->resolve(),
                'followers' => $user->followers_count,
                'is_following' => in_array($user->id, $following, true),
                'follows_you' => in_array($user->id, $followers, true),
            ])->values(),
        ]);
    }

    /** GET /api/users/{username} — everything the Profile page shows, filtered by the owner's privacy settings. */
    public function show(User $user, CurrentUser $current): JsonResponse
    {
        $user->load('preferences');
        $isMe = $current->id() === $user->id;
        $viewer = $current->user();
        $relation = [
            'is_me' => $isMe,
            'is_following' => ! $isMe && $user->isFollowedBy($current->id()),
            'follows_you' => ! $isMe && $viewer !== null && $viewer->isFollowedBy($user->id),
            'my_following' => $viewer?->following()->pluck('users.id') ?? [],
        ];

        // A profile the viewer may not open shows only the name and handle (enough to follow them).
        if (! $this->privacy->canView($user, $current)) {
            return response()->json([
                ...$relation,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'name' => $user->name,
                    'avatar' => null,
                    'bio' => null,
                    'location' => null,
                    'profile_visibility' => $user->profile_visibility,
                    'member_since' => null,
                ],
                'counts' => null,
                'restricted' => true,
            ]);
        }

        $shows = fn (string $section) => $this->privacy->shows($user, $current, $section);
        $approvedOnly = ! $this->privacy->seesUnapprovedReviews($user, $current);
        $reviewQuery = fn () => $user->reviews()->when($approvedOnly, fn ($q) => $q->where('is_approved', true));
        $listQuery = fn () => $user->movieLists()->when(! $isMe, fn ($q) => $q->where('is_public', true));
        // Sections the owner hid come back as null (counts) or empty (content).
        $count = fn (string $section, callable $query) => $shows($section) ? $query()->count() : null;

        return response()->json([
            ...$relation,
            'user' => [
                ...(new MemberResource($user))->resolve(),
                'bio' => $user->bio,
                'location' => $user->location,
                'profile_visibility' => $user->profile_visibility,
                'member_since' => $user->created_at?->toJSON(),
            ],
            'counts' => [
                // The follower count stays visible so the Follow button can show it.
                'followers' => $user->followers()->count(),
                'following' => $count('following', fn () => $user->following()),
                'reviews' => $count('reviews', $reviewQuery),
                'lists' => $count('lists', $listQuery),
                'films' => $count('history', fn () => $user->watchlists()->where('status', 'watched')),
            ],
            'restricted' => false,
            'activity' => $this->activity->forUsers([$user->id], $current, 20),
            'reviews' => ReviewResource::collection($shows('reviews') ? $reviewQuery()->with('user')->latest()->limit(20)->get() : []),
            'lists' => MovieListResource::collection($shows('lists') ? $listQuery()->with(['user', 'items'])->withCount('items')->latest('updated_at')->get() : []),
            'watched' => $shows('history') ? $user->watchlists()->where('status', 'watched')->latest('watched_at')->limit(24)->pluck('tmdb_id') : [],
            // Highest-rated films first; ties go to the most recent review.
            'top4' => $shows('ratings') ? $reviewQuery()->orderByDesc('rating')->latest()->limit(4)->pluck('tmdb_id') : [],
            'genres' => $user->preferences?->favorite_genres ?? [],
            'followers' => $shows('following') ? MemberResource::collection($user->followers()->limit(8)->get()) : [],
            'following' => $shows('following') ? MemberResource::collection($user->following()->limit(8)->get()) : [],
        ]);
    }

    /** GET /api/users/{username}/followers */
    public function followers(User $user, CurrentUser $current): JsonResponse
    {
        abort_unless($this->privacy->shows($user, $current, 'following'), 403);

        return response()->json(['users' => MemberResource::collection($user->followers()->orderBy('name')->get())]);
    }

    /** GET /api/users/{username}/following */
    public function following(User $user, CurrentUser $current): JsonResponse
    {
        abort_unless($this->privacy->shows($user, $current, 'following'), 403);

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
