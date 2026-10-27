<?php

namespace App\Review\Providers;

use App\Review\InterfaceRepository\ReviewRepositoryInterface;
use App\Review\InterfaceServices\ReviewServiceInterface;
use App\Review\Repository\ReviewRepository;
use App\Review\Services\ReviewService;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Review module's interfaces to their implementations.
 */
class ReviewModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        ReviewRepositoryInterface::class => ReviewRepository::class,
        ReviewServiceInterface::class => ReviewService::class,
    ];
}
