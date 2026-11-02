<?php

namespace App\MovieRecommendation\Support;

final class WatchedPeople
{
    /** Count each person once per title, with the same tie-breaking in the header and shelf. */
    public static function leaders(array $titleCredits): array
    {
        $counts = ['actor' => [], 'director' => []];
        foreach ($titleCredits as $credits) {
            $roles = [
                'actor' => $credits['cast'] ?? [],
                'director' => array_filter($credits['crew'] ?? [], fn (array $person): bool => ($person['job'] ?? null) === 'Director'),
            ];
            foreach ($roles as $role => $people) {
                foreach (collect($people)->unique('id') as $person) {
                    $counts[$role][$person['id']] ??= ['id' => $person['id'], 'name' => $person['name'], 'watched_count' => 0];
                    $counts[$role][$person['id']]['watched_count']++;
                }
            }
        }
        $top = fn (string $role): ?array => collect($counts[$role])->sort(fn (array $a, array $b): int => ($b['watched_count'] <=> $a['watched_count']) ?: ($a['id'] <=> $b['id']))->first();

        return ['director' => $top('director'), 'actor' => $top('actor')];
    }
}
