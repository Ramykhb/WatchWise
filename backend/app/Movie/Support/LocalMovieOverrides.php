<?php

namespace App\Movie\Support;

use App\Models\LocalMovie;
use App\Support\MovieGenres;

/** Overlay explicit edits on a copy of a provider payload, never on its cached original. */
final class LocalMovieOverrides
{
    public static function apply(array $payload, ?LocalMovie $local): array
    {
        if ($local === null) {
            return $payload;
        }
        $fields = $local->contentOverrideFields();
        foreach (array_intersect($fields, ['title', 'overview', 'runtime']) as $field) {
            $payload[$field] = $local->{$field};
        }
        if (in_array('release_date', $fields, true)) {
            $payload['release_date'] = $local->release_date?->toDateString();
        }
        if (in_array('genre_ids', $fields, true)) {
            $names = array_replace(array_flip(MovieGenres::IDS), array_column($payload['genres'] ?? [], 'name', 'id'));
            $payload['genre_ids'] = $local->genre_ids ?? [];
            $payload['genres'] = array_map(fn (int $id): array => ['id' => $id, 'name' => $names[$id] ?? "Genre $id"], $payload['genre_ids']);
        }
        if (in_array('director', $fields, true)) {
            $crew = collect($payload['credits']['crew'] ?? []);
            // Keep a real identity only when the edited name still names that TMDB director.
            $match = $crew->first(fn (array $person): bool => ($person['job'] ?? null) === 'Director'
                && mb_strtolower($person['name']) === mb_strtolower($local->director ?? ''));
            $crew = $crew->reject(fn (array $person): bool => ($person['job'] ?? null) === 'Director');
            if (filled($local->director)) {
                $crew->push(['id' => $match['id'] ?? null, 'name' => $local->director, 'job' => 'Director']);
            }
            $payload['credits']['crew'] = $crew->values()->all();
        }

        return $payload;
    }
}
