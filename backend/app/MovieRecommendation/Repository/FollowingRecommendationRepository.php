<?php

namespace App\MovieRecommendation\Repository;

use App\Models\Review;
use App\Models\User;
use App\MovieRecommendation\InterfaceRepository\FollowingRecommendationRepositoryInterface;
use App\Support\MediaType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class FollowingRecommendationRepository implements FollowingRecommendationRepositoryInterface
{
    public function candidates(User $viewer): array
    {
        // Recheck follows, privacy and moderation on every request. Caching this aggregate
        // would continue exposing ratings after an unfollow or a privacy change.
        return Review::query()
            ->where('rating', 5)
            ->where('is_approved', true)
            ->whereNull('removed_at')
            ->whereIn('media_type', MediaType::ALL)
            ->where('user_id', '!=', $viewer->id)
            ->whereHas('user', fn (Builder $authors) => $authors
                ->where('is_active', true)
                ->whereIn('profile_visibility', ['public', 'followers'])
                ->whereHas('followers', fn (Builder $followers) => $followers->whereKey($viewer->id))
                ->whereDoesntHave('preferences', fn (Builder $preferences) => $preferences->where('settings->privacy->ratings', false)))
            ->whereNotExists(fn (QueryBuilder $watched) => $watched->selectRaw('1')->from('watchlists')
                ->where('user_id', $viewer->id)->where('status', 'watched')
                ->whereColumn('watchlists.tmdb_id', 'reviews.tmdb_id')
                ->whereColumn('watchlists.media_type', 'reviews.media_type'))
            ->select(['tmdb_id', 'media_type'])
            ->selectRaw('COUNT(DISTINCT user_id) as following_count, MAX(updated_at) as latest_rating_at')
            ->groupBy('tmdb_id', 'media_type')
            ->orderByDesc('following_count')->orderByDesc('latest_rating_at')
            ->orderBy('media_type')->orderBy('tmdb_id')
            // Extra candidates allow the service to skip titles removed from TMDB.
            ->limit(18)->get()
            ->map(fn (Review $row): array => [
                'tmdb_id' => (int) $row->tmdb_id,
                'media_type' => $row->media_type,
                'following_count' => (int) $row->following_count,
            ])->all();
    }
}
