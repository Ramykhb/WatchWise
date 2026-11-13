<?php

namespace App\User\Repository;

use App\Models\Review;
use App\Models\User;
use App\Support\HiddenMovies;
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
        // Star ratings (no text, or empty text) aren't reviews; same rule as the Home feed.
        return $this->reviews($user, $approvedOnly)->withText()->count();
    }

    public function topRatedTmdbIds(User $user, bool $approvedOnly, int $limit, HiddenMovies $hidden): SupportCollection
    {
        // Movies only: the profile shows these ids as movie posters (a TV show can share the id).
        return $hidden->exclude($this->reviews($user, $approvedOnly)->where('media_type', 'movie'))->orderByDesc('rating')->latest()->limit($limit)->pluck('tmdb_id');
    }

    public function listsCount(User $user, bool $publicOnly): int
    {
        return $user->movieLists()->when($publicOnly, fn ($builder) => $builder->where('is_public', true))->count();
    }

    public function lists(User $user, bool $publicOnly, HiddenMovies $hidden): Collection
    {
        $visibleItems = fn ($items) => $hidden->exclude($items);

        return $user->movieLists()->when($publicOnly, fn ($builder) => $builder->where('is_public', true))
            ->with(['user', 'items' => fn ($items) => $hidden->exclude($items)->limit(5)])->withCount(['items' => $visibleItems])->latest('updated_at')->get();
    }

    public function watchedCount(User $user, HiddenMovies $hidden): int
    {
        return $hidden->exclude($user->watchlists()->where('status', 'watched'))->count();
    }

    public function watchedTmdbIds(User $user, int $limit, HiddenMovies $hidden): SupportCollection
    {
        // Movies only, like top 4 (shown as movie tiles).
        return $hidden->exclude($user->watchlists()->where('status', 'watched')->where('media_type', 'movie'))->latest('watched_at')->limit($limit)->pluck('tmdb_id');
    }

    public function recentFollowers(User $user, int $limit): Collection
    {
        return $user->followers()->orderByDesc('follows.id')->limit($limit)->get();
    }

    public function recentFollowing(User $user, int $limit): Collection
    {
        return $user->following()->orderByDesc('follows.id')->limit($limit)->get();
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
        $text = ltrim($query->q, '@');
        $like = LikePattern::contains($text);
        // Handles are stored lowercase, so "@Mali" finds "mali" whatever the database collation.
        $handleLike = LikePattern::contains(mb_strtolower($text));

        return User::query()
            ->where('is_active', true)
            ->whereKeyNot($exceptUserId)
            ->when($query->q !== '', fn ($builder) => $builder->where(fn ($where) => $where
                ->whereRaw("name LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("username LIKE ? ESCAPE '!'", [$handleLike])));
    }

    /**
     * @return HasMany<Review, User>
     */
    private function reviews(User $user, bool $approvedOnly)
    {
        return $user->reviews()->when($approvedOnly, fn ($builder) => $builder->where('is_approved', true));
    }
}
