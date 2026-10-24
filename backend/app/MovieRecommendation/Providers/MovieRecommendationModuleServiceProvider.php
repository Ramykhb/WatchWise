<?php

namespace App\MovieRecommendation\Providers;

use App\MovieRecommendation\InterfaceServices\RecommendationServiceInterface;
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
    ];
}
