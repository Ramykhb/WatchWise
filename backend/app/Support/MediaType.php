<?php

namespace App\Support;

/**
 * Movie or TV show. TMDB numbers them separately, so a tmdb_id only names a title together with its
 * type. Rows that store a film (ratings, watchlist) keep both; `movie` is the default everywhere.
 */
final class MediaType
{
    public const MOVIE = 'movie';

    public const TV = 'tv';

    /** @var list<string> */
    public const ALL = [self::MOVIE, self::TV];

    /** How the frontend names a title: "550" for a movie, "tv-1399" for a show (as in its URLs). */
    public static function key(string $type, int $tmdbId): string
    {
        return $type === self::TV ? "tv-{$tmdbId}" : (string) $tmdbId;
    }
}
