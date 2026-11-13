<?php

namespace App\User\Services;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\User;
use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use App\MovieList\Resources\MovieListResource;
use App\Notification\InterfaceServices\NotificationServiceInterface;
use App\Report\DTOs\Requests\ReportContentData;
use App\Report\DTOs\Responses\ReportContentResponse;
use App\Report\InterfaceServices\ReportServiceInterface;
use App\Report\Support\ReportTargetType;
use App\Support\HiddenMovies;
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

class ProfileService implements ProfileServiceInterface
{
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
            'lists' => MovieListResource::collection($shows('lists') ? $this->profiles->lists($user, $publicListsOnly, $hidden) : []),
            'watched' => $shows('history') ? $this->profiles->watchedTmdbIds($user, 24, $hidden) : [],
            // Highest-rated films first; ties go to the most recent review.
            'top4' => $shows('ratings') ? $this->profiles->topRatedTmdbIds($user, $approvedOnly, 4, $hidden) : [],
            'genres' => $user->preferences?->favorite_genres ?? [],
            'followers' => $shows('following') ? MemberResource::collection($this->profiles->recentFollowers($user, 8)) : [],
            'following' => $shows('following') ? MemberResource::collection($this->profiles->recentFollowing($user, 8)) : [],
        ]);
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
     * @throws ProfileSectionHidden
     */
    private function ensureConnectionsVisible(User $user, CurrentUser $viewer): void
    {
        if (! $this->privacy->shows($user, $viewer, 'following')) {
            throw new ProfileSectionHidden;
        }
    }
}
