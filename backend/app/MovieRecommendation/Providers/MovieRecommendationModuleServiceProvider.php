<?php

namespace App\MovieRecommendation\Providers;

use App\MovieRecommendation\InterfaceRepository\FollowingRecommendationRepositoryInterface;
use App\MovieRecommendation\InterfaceServices\FollowingRecommendationServiceInterface;
use App\MovieRecommendation\InterfaceServices\RecommendationServiceInterface;
use App\MovieRecommendation\Repository\FollowingRecommendationRepository;
use App\MovieRecommendation\Services\FollowingRecommendationService;
use App\MovieRecommendation\Services\RecommendationService;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the MovieRecommendation module's interfaces to their implementations.
 */
class MovieRecommendationModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        RecommendationServiceInterface::class => RecommendationService::class,
        FollowingRecommendationServiceInterface::class => FollowingRecommendationService::class,
        FollowingRecommendationRepositoryInterface::class => FollowingRecommendationRepository::class,
    ];
}
