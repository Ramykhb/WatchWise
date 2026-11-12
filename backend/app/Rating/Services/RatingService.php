<?php

namespace App\Rating\Services;

use App\Models\Review;
use App\Models\User;
use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use App\Rating\DTOs\Requests\RatingBurstQuery;
use App\Rating\DTOs\Requests\RatingQuery;
use App\Rating\InterfaceRepository\RatingRepositoryInterface;
use App\Rating\InterfaceServices\RatingServiceInterface;
use App\Rating\Support\Stars;
use App\Support\MediaType;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class RatingService implements RatingServiceInterface
{
    public function __construct(
        private readonly RatingRepositoryInterface $ratings,
        private readonly MovieCatalogServiceInterface $catalog,
    ) {}

    public function forMovie(User $user, int $tmdbId, string $mediaType = MediaType::MOVIE): ?int
    {
        return $this->ratings->find($user, $tmdbId, $mediaType);
    }

    public function rate(User $user, int $tmdbId, int $rating, string $mediaType = MediaType::MOVIE): int
    {
        $this->ratings->save($user, $tmdbId, $mediaType, $rating);
        // The local catalogue (admin "Movie data") only holds movies.
        if ($mediaType === MediaType::MOVIE) {
            $this->catalog->remember($tmdbId);
        }

        return $rating;
    }

    public function page(RatingQuery $query): LengthAwarePaginator
    {
        return $this->ratings->paginate($query);
    }

    public function summary(RatingQuery $query): array
    {
        $counts = $this->ratings->starCounts($query);
        $distribution = [];
        $total = 0;
        $stars = 0;

        foreach (Stars::all() as $value) {
            $distribution[$value] = $counts[$value] ?? 0;
            $total += $distribution[$value];
            $stars += $value * $distribution[$value];
        }

        return [
            'total' => $total,
            'average' => $total > 0 ? round($stars / $total, 2) : null,
            'distribution' => $distribution,
        ];
    }

    public function filmPicture(int $tmdbId, string $mediaType): array
    {
        $range = $this->ratings->ratedRange($tmdbId, $mediaType);

        return [
            ...$this->summary(new RatingQuery(tmdbId: $tmdbId, mediaType: $mediaType)),
            'with_text_count' => $this->ratings->withTextCount($tmdbId, $mediaType),
            'first_rated_at' => $range['first'],
            'last_rated_at' => $range['last'],
        ];
    }

    public function bursts(RatingBurstQuery $query, CarbonInterface $until): LengthAwarePaginator
    {
        return $this->ratings->paginateBursts($until->copy()->subHours($query->hours), $until, $query->min, $query->page, $query->perPage);
    }

    public function lockForUpdate(Review $rating): Review
    {
        return $this->ratings->lockForUpdate($rating);
    }

    public function remove(Review $rating): int
    {
        return $this->ratings->delete($rating);
    }

    public function removeAllOf(User $member): array
    {
        return $this->ratings->deleteAllOf($member);
    }
}
