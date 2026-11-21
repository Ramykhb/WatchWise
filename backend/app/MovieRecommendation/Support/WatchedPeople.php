<?php

namespace App\MovieRecommendation\Support;

final class WatchedPeople
{
    /** Count each person once per title, with the same tie-breaking in the header and shelf. */
    public static function leaders(array $titleCredits, array $weights = []): array
    {
        $counts = ['actor' => [], 'director' => []];
        foreach ($titleCredits as $key => $credits) {
            $roles = [
                'actor' => $credits['cast'] ?? [],
                'director' => array_filter($credits['crew'] ?? [], fn (array $person): bool => ($person['job'] ?? null) === 'Director'),
            ];
            foreach ($roles as $role => $people) {
                foreach (collect($people)->unique('id') as $person) {
                    $counts[$role][$person['id']] ??= ['id' => $person['id'], 'name' => $person['name'], 'watched_count' => 0, 'score' => 0];
                    $counts[$role][$person['id']]['watched_count']++;
                    $counts[$role][$person['id']]['score'] += $weights[$key] ?? 1;
                }
            }
        }
        $top = fn (string $role): ?array => collect($counts[$role])->sort(fn (array $a, array $b): int => ($b['score'] <=> $a['score']) ?: ($b['watched_count'] <=> $a['watched_count']) ?: ($a['id'] <=> $b['id']))->first();

        return collect(['director' => $top('director'), 'actor' => $top('actor')])
            ->map(fn (?array $person): ?array => $person === null ? null : array_diff_key($person, ['score' => true]))->all();
    }
}
