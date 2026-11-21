<?php

namespace App\Support;

/** Stable TMDB movie genre names, also used when a local edit introduces a new genre. */
final class MovieGenres
{
    public const IDS = [
        'Action' => 28, 'Adventure' => 12, 'Animation' => 16, 'Comedy' => 35,
        'Crime' => 80, 'Documentary' => 99, 'Drama' => 18, 'Family' => 10751,
        'Fantasy' => 14, 'History' => 36, 'Horror' => 27, 'Music' => 10402,
        'Mystery' => 9648, 'Romance' => 10749, 'Science Fiction' => 878,
        'Thriller' => 53, 'War' => 10752, 'Western' => 37, 'TV Movie' => 10770,
    ];
}
