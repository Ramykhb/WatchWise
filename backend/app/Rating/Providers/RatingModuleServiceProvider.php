<?php

namespace App\Rating\Providers;

use App\Rating\InterfaceRepository\RatingRepositoryInterface;
use App\Rating\InterfaceServices\RatingServiceInterface;
use App\Rating\Repository\RatingRepository;
use App\Rating\Services\RatingService;
use Illuminate\Support\ServiceProvider;

class RatingModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        RatingRepositoryInterface::class => RatingRepository::class,
        RatingServiceInterface::class => RatingService::class,
    ];
}
