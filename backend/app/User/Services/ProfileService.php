<?php

namespace App\User\Services;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\MovieList;
use App\Models\Review;
use App\Models\User;
use App\Models\Watchlist;
use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use App\MovieList\Resources\MovieListResource;
use App\Notification\InterfaceServices\NotificationServiceInterface;
use App\Report\DTOs\Requests\ReportContentData;
use App\Report\DTOs\Responses\ReportContentResponse;
use App\Report\InterfaceServices\ReportServiceInterface;
use App\Report\Support\ReportTargetType;
use App\Support\HiddenMovies;
use App\Support\MediaType;
use App\User\DTOs\Requests\UserSearchQuery;
use App\User\DTOs\Responses\FollowResponse;
use App\User\DTOs\Responses\ProfileResponse;
use App\User\DTOs\Responses\UserSearchResponse;
use App\User\Exceptions\FollowRequestNotFound;
use App\User\Exceptions\ProfileSectionHidden;
use App\User\InterfaceRepository\ProfileRepositoryInterface;
use App\User\InterfaceServices\ProfileServiceInterface;
use App\User\Resources\MemberResource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\Cursor;

class ProfileService implements ProfileServiceInterface
{
    /** How many items the Activity and Reviews tabs show. */
    private const RECENT = 20;

    public function __construct(
        private readonly ProfileRepositoryInterface $profiles,
        private readonly ProfilePrivacy $privacy,
        private readonly NotificationServiceInterface $notifications,
        private readonly MovieCatalogServiceInterface $catalog,
        private readonly ReportServiceInterface $reports,
    ) {}

    public function followCounts(User $user): array
    {
        return ['followers' => $this->profiles->followersCount($user), 'following' => $this->profiles->followingCount($user)];
    }

    public function search(UserSearchQuery $query, CurrentUser $viewer): UserSearchResponse
    {
        $total = $this->profiles->countMatching($query, $viewer->id());
        $users = $this->profiles->search($query, $viewer->id());

        $me = $viewer->user();
        $following = $this->profiles->followedAmong($me, $users->pluck('id'));
        $followers = $this->profiles->followersAmong($me, $users->pluck('id'));
        $requested = $this->notifications->followRequestedAmong($me, $users->modelKeys());

        return new UserSearchResponse($total, $users->map(function (User $user) use ($viewer, $following, $followers, $requested) {
            $member = (new MemberResource($user))->resolve();
            // Match profile(): restricted profiles expose the name and handle, but not the avatar.
            if (! $this->privacy->canView($user, $viewer)) {
                $member['avatar'] = null;
            }

            return [
                ...$member,
                'followers' => $user->followers_count,
                'is_following' => in_array($user->id, $following, true),
                'is_requested' => in_array($user->id, $requested, true),
                'follows_you' => in_array($user->id, $followers, true),
            ];
        })->values()->all());
    }

    public function profile(User $user, CurrentUser $viewer): ProfileResponse
    {
        $this->profiles->loadPreferences($user);
        $isMe = $viewer->id() === $user->id;
        $me = $viewer->user();
        $isFollowing = ! $isMe && $user->isFollowedBy($viewer->id());
        $relation = [
            'is_me' => $isMe,
            'is_following' => $isFollowing,
            // Waiting for a followers-only or private profile to accept.
            'is_requested' => ! $isMe && ! $isFollowing && $this->notifications->hasRequestedFollow($me, $user),
            'follows_you' => ! $isMe && $me->isFollowedBy($user->id),
            'my_following' => $this->profiles->followingIds($me),
        ];

        // Decided once: a followers-only profile checks the follow with a query.
        $canView = $this->privacy->canView($user, $viewer);

        // A profile the viewer may not open shows only the name and handle (enough to follow them).
        if (! $canView) {
            return new ProfileResponse([
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

        $shows = $this->privacy->sectionsShownTo($user, $viewer, $canView);
        $approvedOnly = ! $this->privacy->seesUnapprovedReviews($user, $viewer);
        $publicListsOnly = ! $isMe;
        // Hidden movies stay on your own profile; other members' profiles leave them out.
        $hidden = $isMe ? HiddenMovies::none() : $this->catalog->hiddenMovies();
        // Sections the owner hid come back as null (counts) or empty (content).
        $count = fn (string $section, callable $count) => $shows($section) ? $count() : null;
        $lists = $shows('lists') ? $this->profiles->lists($user, $publicListsOnly, $hidden) : new Collection;
        $watchedPage = $shows('history') ? $this->watched($user, $viewer) : null;
        $reviews = $shows('reviews') ? $this->profiles->recentReviews($user, $approvedOnly, self::RECENT, $hidden, $viewer->id()) : new Collection;

        return new ProfileResponse([
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
                'followers' => $this->profiles->followersCount($user),
                'following' => $count('following', fn () => $this->profiles->followingCount($user)),
                'reviews' => $count('reviews', fn () => $this->profiles->reviewsCount($user, $approvedOnly)),
                'lists' => $count('lists', fn () => $this->profiles->listsCount($user, $publicListsOnly)),
                'films' => $count('history', fn () => $this->profiles->watchedCount($user, $hidden)),
            ],
            'restricted' => false,
            'activity' => $this->activity($user, $shows, $approvedOnly, $hidden, $reviews, $lists),
            'reviews' => $reviews->map(fn (Review $review) => $this->review($review, $shows('ratings')))->values()->all(),
            'lists' => MovieListResource::collection($lists),
            'watched_page' => $watchedPage,
            'watched' => $shows('history') ? $this->profiles->watchedTmdbIds($user, 24, $hidden) : [],
            // Highest-rated films first; ties go to the most recent review.
            'top4' => $shows('ratings') ? $this->profiles->topRatedTmdbIds($user, $approvedOnly, 4, $hidden) : [],
            'genres' => $user->preferences?->favorite_genres ?? [],
            'followers' => $shows('following') ? MemberResource::collection($this->profiles->recentFollowers($user, 8)) : [],
            'following' => $shows('following') ? MemberResource::collection($this->profiles->recentFollowing($user, 8)) : [],
        ]);
    }

    public function watched(User $user, CurrentUser $viewer, ?Cursor $cursor = null): array
    {
        if (! $this->privacy->shows($user, $viewer, 'history')) {
            throw new ProfileSectionHidden;
        }
        $hidden = $viewer->id() === $user->id ? HiddenMovies::none() : $this->catalog->hiddenMovies();
        $page = $this->profiles->watchedPage($user, $hidden, $cursor);

        return ['items' => collect($page->items())->map(fn (Watchlist $item): array => [
            'id' => $item->id, 'tmdb_id' => $item->tmdb_id, 'media_type' => $item->media_type,
            'watched_at' => ($item->watched_at ?? $item->created_at)?->toJSON(),
        ])->all(), 'next_cursor' => $page->nextCursor()?->encode(), 'has_more' => $page->hasMorePages()];
    }

    public function followers(User $user, CurrentUser $viewer): Collection
    {
        $this->ensureConnectionsVisible($user, $viewer);

        return $this->profiles->allFollowers($user);
    }

    public function following(User $user, CurrentUser $viewer): Collection
    {
        $this->ensureConnectionsVisible($user, $viewer);

        return $this->profiles->allFollowing($user);
    }

    public function follow(User $follower, User $target): FollowResponse
    {
        if ($target->isFollowedBy($follower->id)) {
            return new FollowResponse(true, false, $this->profiles->followersCount($target));
        }

        if ($this->privacy->needsFollowApproval($target)) {
            $this->notifications->requestFollow($follower, $target);

            return new FollowResponse(false, true, $this->profiles->followersCount($target));
        }

        if ($this->profiles->follow($follower, $target)) {
            // A request sent while the profile was still followers-only or private is answered by this.
            $this->notifications->removeFollowRequest($follower, $target);
            $this->notifications->followed($follower, $target);
        }

        return new FollowResponse(true, false, $this->profiles->followersCount($target));
    }

    public function report(User $profile, User $reporter, ReportContentData $data): ReportContentResponse
    {
        return $this->reports->report($reporter, ReportTargetType::Profile, $profile->id, $data);
    }

    public function unfollow(User $follower, User $target): FollowResponse
    {
        $this->profiles->unfollow($follower, $target);
        $this->notifications->removeFollowRequest($follower, $target);

        return new FollowResponse(false, false, $this->profiles->followersCount($target));
    }

    public function acceptFollowRequest(User $me, User $requester): void
    {
        if (! $this->notifications->hasRequestedFollow($requester, $me)) {
            throw new FollowRequestNotFound;
        }

        $this->profiles->follow($requester, $me);
        $this->notifications->acceptFollowRequest($requester, $me);
    }

    public function declineFollowRequest(User $me, User $requester): void
    {
        $this->notifications->removeFollowRequest($requester, $me);
    }

    /**
     * The Activity tab: reviews, ratings, public lists and queued titles the viewer may see, newest first.
     *
     * @param  callable(string): bool  $shows
     * @param  Collection<int, Review>  $reviews
     * @param  Collection<int, MovieList>  $lists
     * @return list<array<string, mixed>>
     */
    private function activity(User $user, callable $shows, bool $approvedOnly, HiddenMovies $hidden, Collection $reviews, Collection $lists): array
    {
        $member = (new MemberResource($user))->resolve();
        $showsRatings = $shows('ratings');
        $item = fn (string $id, string $kind, ?int $tmdbId, ?string $mediaType, $at, array $extra = []) => [
            'id' => $id,
            'kind' => $kind,
            'user' => $member,
            'tmdb_id' => $tmdbId,
            'media_type' => $mediaType ?? MediaType::MOVIE,
            'created_at' => $at->toJSON(),
            ...$extra,
        ];

        $ratings = $showsRatings ? $this->profiles->recentRatings($user, $approvedOnly, self::RECENT, $hidden) : new Collection;
        $queued = $shows('watchlist') ? $this->profiles->recentWatchlist($user, self::RECENT, $hidden) : new Collection;

        return collect()
            ->concat($reviews->map(fn (Review $review) => $item('review:'.$review->id, 'reviewed', $review->tmdb_id, $review->media_type, $review->updated_at,
                ['rating' => $showsRatings ? $review->rating : 0, 'review' => $this->review($review, $showsRatings)])))
            ->concat($ratings->map(fn (Review $rating) => $item('rating:'.$rating->id, 'rated', $rating->tmdb_id, $rating->media_type, $rating->updated_at,
                ['rating' => $rating->rating])))
            // Only public lists are activity, also on your own profile.
            ->concat($lists->where('is_public', true)->map(fn (MovieList $list) => $item('list:'.$list->id, 'list', $list->items->first()?->tmdb_id, $list->items->first()?->media_type, $list->updated_at,
                ['list' => (new MovieListResource($list))->resolve()])))
            ->concat($queued->map(fn (Watchlist $entry) => $item('watchlist:'.$entry->id, 'watchlist', $entry->tmdb_id, $entry->media_type, $entry->created_at)))
            ->sortByDesc('created_at')->take(self::RECENT)->values()->all();
    }

    /**
     * A written review as the profile's Reviews tab and activity cards show it.
     *
     * @return array<string, mixed>
     */
    private function review(Review $review, bool $showsRating): array
    {
        return [
            'id' => $review->id,
            'tmdb_id' => $review->tmdb_id,
            'media_type' => $review->media_type ?? MediaType::MOVIE,
            // A member who hides their ratings keeps the stars off their reviews too (same rule as the Home feed).
            'rating' => $showsRating ? $review->rating : 0,
            'sentiment' => $review->sentiment,
            'content' => $review->content,
            'has_spoilers' => (bool) $review->has_spoilers,
            // Same fields as the Movie page's reviews, so the Helpful button shows and saves the real votes.
            'helpful' => (int) ($review->votes_count ?? 0),
            'marked_helpful' => (bool) ($review->voted ?? false),
            'author' => (new MemberResource($review->user))->resolve(),
            'created_at' => $review->created_at?->toJSON(),
        ];
    }

    /**
     * @throws ProfileSectionHidden
     */
    private function ensureConnectionsVisible(User $user, CurrentUser $viewer): void
    {
        if (! $this->privacy->shows($user, $viewer, 'following')) {
            throw new ProfileSectionHidden;
        }
    }
}
