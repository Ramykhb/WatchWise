<?php

namespace App\Admin\Repository;

use App\Admin\InterfaceRepository\AdminStatsRepositoryInterface;
use App\Models\Message;
use App\Models\MovieList;
use App\Models\Review;
use App\Models\User;
use App\Models\Watchlist;
use App\Support\MediaType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class AdminStatsRepository implements AdminStatsRepositoryInterface
{
    public function usersTotal(): int
    {
        return User::count();
    }

    public function usersCreatedSince(CarbonInterface $since): int
    {
        return User::where('created_at', '>=', $since)->count();
    }

    public function reviewsTotal(): int
    {
        return $this->withText()->count();
    }

    public function reviewsPending(): int
    {
        return $this->withText()->where('is_approved', false)->count();
    }

    public function reviewsCreatedSince(CarbonInterface $since): int
    {
        return $this->withText()->where('created_at', '>=', $since)->count();
    }

    public function usersCreatedBetween(CarbonInterface $from, CarbonInterface $until): int
    {
        return User::where('created_at', '>=', $from)->where('created_at', '<', $until)->count();
    }

    public function reviewsCreatedBetween(CarbonInterface $from, CarbonInterface $until): int
    {
        return $this->withText()->where('created_at', '>=', $from)->where('created_at', '<', $until)->count();
    }

    public function usersActiveBetween(CarbonInterface $from, CarbonInterface $until): int
    {
        return User::where('last_active_at', '>=', $from)->where('last_active_at', '<', $until)->count();
    }

    public function mostWatched(array $excludedTmdbIds, int $limit): array
    {
        return Watchlist::query()
            ->where('status', 'watched')
            ->where('media_type', MediaType::MOVIE)
            ->whereNotIn('tmdb_id', $excludedTmdbIds)
            ->selectRaw('tmdb_id, count(*) as total')
            ->groupBy('tmdb_id')
            ->orderByDesc('total')
            ->orderBy('tmdb_id')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => ['tmdb_id' => (int) $row->tmdb_id, 'count' => (int) $row->total])
            ->all();
    }

    public function topRated(array $excludedTmdbIds, int $minRatings, int $limit): array
    {
        return Review::query()
            ->where('is_approved', true)
            ->where('media_type', MediaType::MOVIE)
            ->whereNotIn('tmdb_id', $excludedTmdbIds)
            ->selectRaw('tmdb_id, count(*) as total, avg(rating) as average')
            ->groupBy('tmdb_id')
            ->havingRaw('count(*) >= ?', [$minRatings])
            ->orderByDesc('average')
            ->orderByDesc('total')
            ->orderBy('tmdb_id')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'tmdb_id' => (int) $row->tmdb_id,
                'count' => (int) $row->total,
                'average' => round((float) $row->average, 2),
            ])
            ->all();
    }

    public function engagementWeights(array $excludedTmdbIds, int $limit): array
    {
        $watched = Watchlist::query()
            ->where('status', 'watched')
            ->where('media_type', MediaType::MOVIE)
            ->whereNotIn('tmdb_id', $excludedTmdbIds)
            ->selectRaw('tmdb_id, count(*) as total')
            ->groupBy('tmdb_id')
            ->pluck('total', 'tmdb_id');

        $rated = Review::query()
            ->where('is_approved', true)
            ->where('media_type', MediaType::MOVIE)
            ->whereNotIn('tmdb_id', $excludedTmdbIds)
            ->selectRaw('tmdb_id, count(*) as total')
            ->groupBy('tmdb_id')
            ->pluck('total', 'tmdb_id');

        $weights = [];

        foreach ([$watched, $rated] as $counts) {
            foreach ($counts as $tmdbId => $total) {
                $weights[(int) $tmdbId] = ($weights[(int) $tmdbId] ?? 0) + (int) $total;
            }
        }

        arsort($weights);

        return array_slice($weights, 0, $limit, true);
    }

    public function dailyActivity(CarbonInterface $since): array
    {
        return [
            'signups' => $this->perDay(User::query(), 'created_at', $since),
            'ratings' => $this->perDay(Review::query()->where(fn (Builder $query) => $query->whereNull('content')->orWhere('content', '')), 'created_at', $since),
            'reviews' => $this->perDay($this->withText(), 'created_at', $since),
            'lists' => $this->perDay(MovieList::query(), 'created_at', $since),
            'watches' => $this->perDay(Watchlist::query()->where('status', 'watched'), 'watched_at', $since),
            'messages' => $this->perDay(Message::query(), 'created_at', $since),
        ];
    }

    /**
     * @return Builder<Review>
     */
    private function withText(): Builder
    {
        return Review::query()->whereNotNull('content')->where('content', '!=', '');
    }

    /**
     * @param  Builder<*>  $query
     * @return array<string, int>
     */
    private function perDay(Builder $query, string $column, CarbonInterface $since): array
    {
        return $query
            ->where($column, '>=', $since)
            ->selectRaw("DATE({$column}) as day, count(*) as total")
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }
}
