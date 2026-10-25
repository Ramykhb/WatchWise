<?php

namespace App\User\Services;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\User;
use App\MovieList\Resources\MovieListResource;
use App\User\DTOs\Requests\UserSearchQuery;
use App\User\DTOs\Responses\FollowResponse;
use App\User\DTOs\Responses\ProfileResponse;
use App\User\DTOs\Responses\UserSearchResponse;
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
    ) {}

    public function search(UserSearchQuery $query, CurrentUser $viewer): UserSearchResponse
    {
        $total = $this->profiles->countMatching($query, $viewer->id());
        $users = $this->profiles->search($query, $viewer->id());

        $me = $viewer->user();
        $following = $this->profiles->followedAmong($me, $users->pluck('id'));
        $followers = $this->profiles->followersAmong($me, $users->pluck('id'));

        return new UserSearchResponse($total, $users->map(function (User $user) use ($viewer, $following, $followers) {
            $member = (new MemberResource($user))->resolve();
            // Match profile(): restricted profiles expose the name and handle, but not the avatar.
            if (! $this->privacy->canView($user, $viewer)) {
                $member['avatar'] = null;
            }

            return [
                ...$member,
                'followers' => $user->followers_count,
                'is_following' => in_array($user->id, $following, true),
                'follows_you' => in_array($user->id, $followers, true),
            ];
        })->values()->all());
    }

    public function profile(User $user, CurrentUser $viewer): ProfileResponse
    {
        $this->profiles->loadPreferences($user);
        $isMe = $viewer->id() === $user->id;
        $me = $viewer->user();
        $relation = [
            'is_me' => $isMe,
            'is_following' => ! $isMe && $user->isFollowedBy($viewer->id()),
            'follows_you' => ! $isMe && $me !== null && $me->isFollowedBy($user->id),
            'my_following' => $me !== null ? $this->profiles->followingIds($me) : [],
        ];

        // A profile the viewer may not open shows only the name and handle (enough to follow them).
        if (! $this->privacy->canView($user, $viewer)) {
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

        $shows = fn (string $section) => $this->privacy->shows($user, $viewer, $section);
        $approvedOnly = ! $this->privacy->seesUnapprovedReviews($user, $viewer);
        $publicListsOnly = ! $isMe;
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
                'films' => $count('history', fn () => $this->profiles->watchedCount($user)),
            ],
            'restricted' => false,
            'lists' => MovieListResource::collection($shows('lists') ? $this->profiles->lists($user, $publicListsOnly) : []),
            'watched' => $shows('history') ? $this->profiles->watchedTmdbIds($user, 24) : [],
            // Highest-rated films first; ties go to the most recent review.
            'top4' => $shows('ratings') ? $this->profiles->topRatedTmdbIds($user, $approvedOnly, 4) : [],
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
        $this->profiles->follow($follower, $target);

        return new FollowResponse(true, $this->profiles->followersCount($target));
    }

    public function unfollow(User $follower, User $target): FollowResponse
    {
        $this->profiles->unfollow($follower, $target);

        return new FollowResponse(false, $this->profiles->followersCount($target));
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
