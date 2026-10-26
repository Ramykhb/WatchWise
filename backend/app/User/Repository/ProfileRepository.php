<?php

namespace App\User\Repository;

use App\Models\Review;
use App\Models\User;
use App\Support\LikePattern;
use App\User\DTOs\Requests\UserSearchQuery;
use App\User\InterfaceRepository\ProfileRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as SupportCollection;

class ProfileRepository implements ProfileRepositoryInterface
{
    public function countMatching(UserSearchQuery $query, int $exceptUserId): int
    {
        return $this->matching($query, $exceptUserId)->count();
    }

    public function search(UserSearchQuery $query, int $exceptUserId): Collection
    {
        return $this->matching($query, $exceptUserId)
            ->withCount('followers')
            ->when($query->q !== '', fn ($builder) => $builder->orderBy('name'), fn ($builder) => $builder->orderByDesc('followers_count')->orderBy('name'))
            ->limit($query->limit)
            ->get();
    }

    public function followedAmong(User $user, SupportCollection $userIds): array
    {
        return $user->following()->whereIn('users.id', $userIds)->pluck('users.id')->all();
    }

    public function followersAmong(User $user, SupportCollection $userIds): array
    {
        return $user->followers()->whereIn('users.id', $userIds)->pluck('users.id')->all();
    }

    public function followingIds(User $user): SupportCollection
    {
        return $user->following()->pluck('users.id');
    }

    public function loadPreferences(User $user): User
    {
        return $user->load('preferences');
    }

    public function followersCount(User $user): int
    {
        return $user->followers()->count();
    }

    public function followingCount(User $user): int
    {
        return $user->following()->count();
    }

    public function reviewsCount(User $user, bool $approvedOnly): int
    {
        // Star ratings from the Movie page are reviews without text; they aren't counted as reviews.
        return $this->reviews($user, $approvedOnly)->whereNotNull('content')->count();
    }

    public function topRatedTmdbIds(User $user, bool $approvedOnly, int $limit): SupportCollection
    {
        return $this->reviews($user, $approvedOnly)->orderByDesc('rating')->latest()->limit($limit)->pluck('tmdb_id');
    }

    public function listsCount(User $user, bool $publicOnly): int
    {
        return $user->movieLists()->when($publicOnly, fn ($builder) => $builder->where('is_public', true))->count();
    }

    public function lists(User $user, bool $publicOnly): Collection
    {
        return $user->movieLists()->when($publicOnly, fn ($builder) => $builder->where('is_public', true))
            ->with(['user', 'items'])->withCount('items')->latest('updated_at')->get();
    }

    public function watchedCount(User $user): int
    {
        return $user->watchlists()->where('status', 'watched')->count();
    }

    public function watchedTmdbIds(User $user, int $limit): SupportCollection
    {
        return $user->watchlists()->where('status', 'watched')->latest('watched_at')->limit($limit)->pluck('tmdb_id');
    }

    public function recentFollowers(User $user, int $limit): Collection
    {
        return $user->followers()->limit($limit)->get();
    }

    public function recentFollowing(User $user, int $limit): Collection
    {
        return $user->following()->limit($limit)->get();
    }

    public function allFollowers(User $user): Collection
    {
        return $user->followers()->orderBy('name')->get();
    }

    public function allFollowing(User $user): Collection
    {
        return $user->following()->orderBy('name')->get();
    }

    public function follow(User $follower, User $target): bool
    {
        return $follower->following()->syncWithoutDetaching([$target->id])['attached'] !== [];
    }

    public function unfollow(User $follower, User $target): void
    {
        $follower->following()->detach($target->id);
    }

    /**
     * @return Builder<User>
     */
    private function matching(UserSearchQuery $query, int $exceptUserId): Builder
    {
        $like = LikePattern::contains(ltrim($query->q, '@'));

        return User::query()
            ->where('is_active', true)
            ->whereKeyNot($exceptUserId)
            ->when($query->q !== '', fn ($builder) => $builder->where(fn ($where) => $where
                ->whereRaw("name LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("username LIKE ? ESCAPE '!'", [$like])));
    }

    /**
     * @return HasMany<Review, User>
     */
    private function reviews(User $user, bool $approvedOnly)
    {
        return $user->reviews()->when($approvedOnly, fn ($builder) => $builder->where('is_approved', true));
    }
}
