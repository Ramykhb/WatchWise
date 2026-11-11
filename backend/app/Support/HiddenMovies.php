<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The movies an admin hid from browsing. Only movies live in the local catalogue, and TMDB numbers movies and TV
 * shows separately, so a TV show that shares a hidden movie's id is never hidden.
 *
 * Lists built for members go through this one class: `hides()` for payloads in memory, `exclude()` for queries.
 */
final readonly class HiddenMovies
{
    /** @var array<int, true> */
    private array $tmdbIds;

    /**
     * @param  list<int>  $tmdbIds
     */
    public function __construct(array $tmdbIds)
    {
        $this->tmdbIds = array_fill_keys($tmdbIds, true);
    }

    /** Nothing is hidden, e.g. the owner looking at their own lists. */
    public static function none(): self
    {
        return new self([]);
    }

    public function hides(string $mediaType, int $tmdbId): bool
    {
        return $mediaType === MediaType::MOVIE && isset($this->tmdbIds[$tmdbId]);
    }

    /**
     * Leaves hidden movies out of a query or relation over rows that name a title by TMDB id and media type.
     *
     * @template TQuery of Builder|Relation
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public function exclude(Builder|Relation $query, string $idColumn = 'tmdb_id', string $typeColumn = 'media_type'): Builder|Relation
    {
        if ($this->tmdbIds === []) {
            return $query;
        }

        $query->where(fn (Builder $title) => $title
            ->where($typeColumn, '!=', MediaType::MOVIE)
            ->orWhereNotIn($idColumn, array_keys($this->tmdbIds)));

        return $query;
    }
}
