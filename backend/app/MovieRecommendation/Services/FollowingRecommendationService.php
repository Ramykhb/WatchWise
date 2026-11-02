<?php

namespace App\MovieRecommendation\Services;

use App\Models\User;
use App\Movie\Exceptions\MovieNotFound;
use App\Movie\InterfaceServices\MovieServiceInterface;
use App\MovieRecommendation\DTOs\Responses\RecommendationResponse;
use App\MovieRecommendation\InterfaceRepository\FollowingRecommendationRepositoryInterface;
use App\MovieRecommendation\InterfaceServices\FollowingRecommendationServiceInterface;

class FollowingRecommendationService implements FollowingRecommendationServiceInterface
{
    public function __construct(
        private readonly FollowingRecommendationRepositoryInterface $recommendations,
        private readonly MovieServiceInterface $movies,
    ) {}

    public function forUser(User $user): RecommendationResponse
    {
        $movies = [];
        foreach ($this->recommendations->candidates($user) as $candidate) {
            try {
                $movie = $this->movies->find($candidate['tmdb_id'], $candidate['media_type'])->movie;
            } catch (MovieNotFound) {
                continue;
            }
            $count = $candidate['following_count'];
            $movies[] = [
                ...$movie->jsonSerialize(),
                'following_count' => $count,
                'reason' => $count.($count === 1 ? ' person you follow' : ' people you follow').' rated this ★5',
            ];
            if (count($movies) === 6) {
                break;
            }
        }

        return new RecommendationResponse(['movies' => $movies]);
    }
}
