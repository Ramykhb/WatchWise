<?php

namespace App\User\InterfaceRepository;

use App\Models\MovieList;
use App\Models\User;
use App\User\DTOs\Requests\UserSearchQuery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

interface ProfileRepositoryInterface
{
    /** Active members other than `$exceptUserId` whose name or @username contains the text (everyone when it is empty). */
    public function countMatching(UserSearchQuery $query, int $exceptUserId): int;

    /**
     * The first `limit` matches with their follower count: by name for a search, most followed first for suggestions.
     *
     * @return Collection<int, User>
     */
    public function search(UserSearchQuery $query, int $exceptUserId): Collection;

    /**
     * Which of these users `$user` follows.
     *
     * @param  SupportCollection<int, int>  $userIds
     * @return list<int>
     */
    public function followedAmong(User $user, SupportCollection $userIds): array;

    /**
     * Which of these users follow `$user`.
     *
     * @param  SupportCollection<int, int>  $userIds
     * @return list<int>
     */
    public function followersAmong(User $user, SupportCollection $userIds): array;

    /**
     * @return SupportCollection<int, int>
     */
    public function followingIds(User $user): SupportCollection;

    public function loadPreferences(User $user): User;

    public function followersCount(User $user): int;

    public function followingCount(User $user): int;

    public function reviewsCount(User $user, bool $approvedOnly): int;

    /**
     * Highest-rated films first; ties go to the most recent review.
     *
     * @return SupportCollection<int, int>
     */
    public function topRatedTmdbIds(User $user, bool $approvedOnly, int $limit): SupportCollection;

    public function listsCount(User $user, bool $publicOnly): int;

    /**
     * @return Collection<int, MovieList> latest activity first, with owner, films and film count
     */
    public function lists(User $user, bool $publicOnly): Collection;

    public function watchedCount(User $user): int;

    /**
     * @return SupportCollection<int, int> the most recently watched films
     */
    public function watchedTmdbIds(User $user, int $limit): SupportCollection;

    /**
     * @return Collection<int, User>
     */
    public function recentFollowers(User $user, int $limit): Collection;

    /**
     * @return Collection<int, User>
     */
    public function recentFollowing(User $user, int $limit): Collection;

    /**
     * @return Collection<int, User> by name
     */
    public function allFollowers(User $user): Collection;

    /**
     * @return Collection<int, User> by name
     */
    public function allFollowing(User $user): Collection;

    /** Returns true when `$follower` was not following `$target` before. */
    public function follow(User $follower, User $target): bool;

    public function unfollow(User $follower, User $target): void;
}
