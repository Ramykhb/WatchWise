<?php

namespace App\MovieRecommendation\Services;

use App\Models\User;
use App\Movie\InterfaceServices\MovieServiceInterface;
use App\MovieRecommendation\DTOs\Responses\TasteProfileResponse;
use App\MovieRecommendation\InterfaceRepository\TasteProfileRepositoryInterface;
use App\MovieRecommendation\InterfaceServices\TasteProfileServiceInterface;
use App\MovieRecommendation\Support\WatchedPeople;
use App\Support\MediaType;

class TasteProfileService implements TasteProfileServiceInterface
{
    public function __construct(
        private readonly TasteProfileRepositoryInterface $profiles,
        private readonly MovieServiceInterface $movies,
    ) {}

    public function forUser(User $user): TasteProfileResponse
    {
        $profile = $this->profiles->forUser($user);
        $titles = collect($profile['titles'])->keyBy(fn (array $title): string => MediaType::key($title['media_type'], $title['id']));
        $metadata = $titles->isEmpty() ? [] : $this->movies->metadata($titles->values()->all());
        $counts = [];
        foreach ($metadata as $key => $movie) {
            foreach (collect($movie['genres'])->unique('id') as $genre) {
                $id = $genre['id'];
                $counts[$id] ??= ['id' => $id, 'name' => $genre['name'], 'watched_count' => 0, 'types' => []];
                $counts[$id]['watched_count']++;
                $counts[$id]['types'][$titles[$key]['media_type']] = true;
            }
        }
        // A title may have several genres. Percentages represent shares of all genre occurrences.
        $total = array_sum(array_column($counts, 'watched_count'));
        $genres = collect($counts)->sort(fn (array $a, array $b): int => ($b['watched_count'] <=> $a['watched_count']) ?: ($a['id'] <=> $b['id']))
            ->take(3)->map(fn (array $genre): array => [
                'id' => $genre['id'], 'name' => $genre['name'], 'watched_count' => $genre['watched_count'],
                'percentage' => (int) round(100 * $genre['watched_count'] / $total),
                'media_type' => count($genre['types']) === 2 ? 'all' : array_key_first($genre['types']),
            ])->values()->all();
        $people = WatchedPeople::leaders(array_column($metadata, 'credits'));

        return new TasteProfileResponse(
            $titles->where('media_type', 'movie')->count(), $titles->where('media_type', 'tv')->count(),
            $profile['five_star_ratings'], $profile['following_count'], $genres, $people['director'], $people['actor'],
        );
    }
}
